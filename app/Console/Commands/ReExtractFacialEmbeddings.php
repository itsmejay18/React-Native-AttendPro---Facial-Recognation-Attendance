<?php

namespace App\Console\Commands;

use App\Exceptions\PythonRecognitionException;
use App\Models\FacialProfile;
use App\Models\Person;
use App\Models\User;
use App\Services\PythonRecognitionClient;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds facial profiles from the enrollment images shipped in
 * database/seeders/data/attendpro-dataset/ by asking the Python service
 * to re-extract embeddings under the ACTIVE recognition backend.
 *
 * This is the supported path after switching backends (e.g. SFace to
 * InsightFace buffalo_l): old vectors live in a different embedding space
 * and are ignored by the matcher, while the enrollment images are
 * backend-agnostic.
 *
 * Requires the Python recognition service to be running. Restores any
 * missing images from the dataset directory into storage automatically.
 */
class ReExtractFacialEmbeddings extends Command
{
    protected $signature = 'attendpro:faces:re-extract
        {--person= : Only re-extract one institution ID}
        {--keep-old : Keep previous profiles active instead of deactivating them}';

    protected $description = 'Rebuild facial profiles from shipped enrollment images via the Python service';

    public function handle(PythonRecognitionClient $python): int
    {
        $manifestPath = database_path('seeders/data/attendpro-dataset/facial_enrollments.json');
        if (! is_file($manifestPath)) {
            $this->error('Dataset manifest not found. Run the AttendproDatasetSeeder first.');

            return self::FAILURE;
        }

        try {
            $health = data_get($python->health(), 'data', []);
        } catch (PythonRecognitionException $exception) {
            $this->error('Python service is not reachable: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->line('Python backend: '.(data_get($health, 'backend', 'unknown')).' / model: '.(data_get($health, 'model', 'unknown')));

        $sessions = collect(json_decode(file_get_contents($manifestPath), true) ?? []);
        if ($this->option('person')) {
            $sessions = $sessions->where('person_institution_id', $this->option('person'));
            if ($sessions->isEmpty()) {
                $this->error('No enrollment sessions found for that institution ID.');

                return self::FAILURE;
            }
        }

        $created = 0;
        $failed = 0;
        foreach ($sessions as $session) {
            try {
                $created += $this->reExtractSession($python, $session);
            } catch (PythonRecognitionException $exception) {
                $failed++;
                $this->warn("Skipped {$session['person_institution_id']}: ".$exception->getMessage());
            }
        }

        $this->info("Re-extracted {$created} facial samples ({$failed} sessions skipped).");

        return $created > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function reExtractSession(PythonRecognitionClient $python, array $session): int
    {
        $person = Person::query()->where('institution_id', $session['person_institution_id'])->first();
        if (! $person) {
            throw new PythonRecognitionException("Person {$session['person_institution_id']} does not exist.", 422);
        }

        $files = [];
        foreach ($session['images'] as $image) {
            $relative = ltrim($image['path'], '/');
            $stored = storage_path('app/private/'.$relative);
            if (! is_file($stored)) {
                $fallback = database_path('seeders/data/attendpro-dataset/facial-enrollments/'.preg_replace('#^facial-enrollments/#', '', $relative));
                if (is_file($fallback)) {
                    @mkdir(dirname($stored), 0777, true);
                    copy($fallback, $stored);
                }
            }
            if (! is_file($stored)) {
                throw new PythonRecognitionException("Enrollment image is missing: {$relative}.", 422);
            }
            $files[] = new UploadedFile($stored, basename($stored), 'image/jpeg', null, true);
        }

        $extraction = $python->extractBatch($files);
        $samples = data_get($extraction, 'data.samples');
        if (! is_array($samples) || count($samples) === 0) {
            throw new PythonRecognitionException('Python returned no usable face samples.', 422, $extraction);
        }

        $enrolledBy = $session['enrolled_by_email']
            ? User::query()->where('email', $session['enrolled_by_email'])->value('id')
            : null;
        $sessionUuid = (string) Str::uuid();
        $nextVersion = (int) FacialProfile::query()->where('person_id', $person->id)->max('version') + 1;

        $profiles = DB::transaction(function () use ($person, $samples, $files, $session, $sessionUuid, $nextVersion, $enrolledBy): array {
            if (! $this->option('keep-old')) {
                FacialProfile::query()->where('person_id', $person->id)->where('is_active', true)
                    ->get()->each->update(['is_active' => false]);
            }

            return collect($samples)->map(function (array $sample, int $index) use ($person, $files, $session, $sessionUuid, $nextVersion, $enrolledBy): FacialProfile {
                $embedding = $sample['embedding'] ?? null;
                $model = $sample['model'] ?? null;
                if (! is_array($embedding) || count($embedding) < 16 || ! is_string($model) || $model === '') {
                    throw new PythonRecognitionException('Python returned an invalid facial enrollment sample.', 502, $sample);
                }
                $normalized = array_map('floatval', $embedding);
                $checksum = hash('sha256', json_encode($normalized, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

                $frameIndex = (int) ($sample['frame'] ?? $index);
                $imagePath = "facial-enrollments/{$person->id}/{$sessionUuid}/".sprintf('sample-%02d.jpg', $index + 1);
                if (isset($files[$frameIndex]) && is_file($files[$frameIndex]->getRealPath())) {
                    @mkdir(dirname(storage_path('app/private/'.$imagePath)), 0777, true);
                    copy($files[$frameIndex]->getRealPath(), storage_path('app/private/'.$imagePath));
                }

                return FacialProfile::query()->create([
                    'person_id' => $person->id,
                    'embedding' => $normalized,
                    'embedding_checksum' => $checksum,
                    'model' => $model,
                    'dimensions' => count($normalized),
                    'version' => $nextVersion,
                    'enrollment_session_uuid' => $sessionUuid,
                    'consented_at' => $session['consented_at'] ?? now(),
                    'retention_until' => $session['retention_until'] ?? null,
                    'quality_metadata' => [
                        'detection_score' => $sample['detection_score'] ?? null,
                        'quality' => $sample['quality'] ?? null,
                    ],
                    'sample_image_path' => $imagePath,
                    'enrolled_by' => $enrolledBy,
                    'is_active' => true,
                ]);
            })->all();
        });

        try {
            $python->indexProfiles(collect($profiles)->map(fn (FacialProfile $profile) => [
                'profile_id' => $profile->id,
                'person_id' => $person->id,
                'institution_id' => $person->institution_id,
                'person_type' => $person->type,
                'full_name' => $person->full_name,
                'model' => $profile->model,
                'dimensions' => $profile->dimensions,
                'version' => $profile->version,
                'embedding' => $profile->embedding,
                'checksum' => $profile->embedding_checksum,
                'updated_at' => $profile->updated_at->toIso8601String(),
            ])->all());
        } catch (PythonRecognitionException $exception) {
            $this->warn("Profiles saved for {$person->institution_id} but the live index was not updated: ".$exception->getMessage());
        }

        $this->line("{$person->institution_id}: ".count($profiles).' samples ('.($profiles[0]->model ?? '?').').');

        return count($profiles);
    }
}
