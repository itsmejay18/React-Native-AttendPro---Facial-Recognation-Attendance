<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonRecognitionException;
use App\Models\FacialProfile;
use App\Models\Person;
use App\Models\Schedule;
use App\Services\AttendanceService;
use App\Services\AttendanceEmailNotifier;
use App\Services\PythonRecognitionClient;
use App\Services\PythonServiceManager;
use App\Services\RecognitionBackendConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RecognitionBridgeController extends Controller
{
    public function status(PythonRecognitionClient $python): JsonResponse
    {
        return $this->respond(fn () => $python->health());
    }

    public function kioskStatus(PythonRecognitionClient $python): JsonResponse
    {
        try {
            $health = $python->health();
            $service = $health['data'] ?? $health;

            return response()->json([
                'data' => [
                    'status' => $service['status'] ?? 'offline',
                    'configured' => (bool) ($service['configured'] ?? false),
                    // The OpenCV objects are created lazily on the first scan.  A
                    // downloaded model is therefore ready to accept that scan even
                    // while engine_initialized is still false.
                    'models_ready' => (bool) ($service['models_ready']
                        ?? ($service['models_downloaded'] ?? false)),
                    'profiles_loaded' => (int) ($service['profiles_loaded'] ?? 0),
                    'backend' => $service['backend'] ?? null,
                    'last_error' => $service['last_error'] ?? null,
                ],
            ]);
        } catch (PythonRecognitionException $exception) {
            return response()->json([
                'data' => [
                    'status' => 'offline',
                    'configured' => false,
                    'models_ready' => false,
                    'profiles_loaded' => 0,
                    'last_error' => 'The attendance service is offline. Ask an administrator to start it.',
                ],
            ]);
        }
    }

    public function start(Request $request, PythonServiceManager $manager): JsonResponse
    {
        abort_unless((bool) config('attendpro.python_service.managed'), 403);
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);

        return $this->respond(fn () => [
            'message' => 'Python recognition server started.',
            'data' => $manager->start(),
        ]);
    }

    public function switchBackend(
        Request $request,
        RecognitionBackendConfiguration $configuration,
        PythonServiceManager $manager,
    ): JsonResponse {
        abort_unless((bool) config('attendpro.python_service.managed'), 403);
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);

        $validated = $request->validate([
            'backend' => ['required', Rule::in(array_keys(RecognitionBackendConfiguration::BACKENDS))],
        ]);

        return $this->respond(function () use ($validated, $configuration, $manager): array {
            $configuration->set($validated['backend']);
            $service = $manager->restart();

            return [
                'message' => 'Recognition backend saved and the Python server restarted.',
                'data' => $service,
            ];
        });
    }

    public function recognize(
        Request $request,
        PythonRecognitionClient $python,
        AttendanceService $attendance,
        AttendanceEmailNotifier $emails,
    ): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_without:images'],
            'images' => ['nullable', 'array', 'min:1', 'max:30', 'required_without:image'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'direction' => ['required', Rule::in(['auto', 'time_in', 'time_out'])],
            'schedule_id' => [Rule::requiredIf($request->user() !== null), 'nullable', 'integer', 'exists:schedules,id'],
        ]);

        return $this->respond(function () use ($python, $attendance, $emails, $validated): array {
            $recognitionResponse = ! empty($validated['images'] ?? [])
                ? $python->recognizeBatch($validated['images'], $validated['direction'])
                : $python->recognize($validated['image'], $validated['direction']);
            $recognition = $recognitionResponse['data'] ?? $recognitionResponse;

            $payload = [
                'event_id' => data_get($recognition, 'recognition.event_id'),
                'result' => $recognition['result'] ?? 'unknown',
                'institution_id' => $recognition['institution_id'] ?? null,
                'confidence' => $recognition['confidence'] ?? null,
                'direction' => $recognition['direction'] ?? $validated['direction'],
                'captured_at' => $recognition['captured_at'] ?? now()->toIso8601String(),
                'metadata' => $recognition['metadata'] ?? null,
            ];

            if (! is_string($payload['event_id']) || $payload['event_id'] === '') {
                throw new PythonRecognitionException('Python returned an invalid recognition event.', 502, $recognitionResponse);
            }

            if ($payload['result'] !== 'matched') {
                unset($payload['institution_id']);
            }

            $selectedSchedule = isset($validated['schedule_id'])
                ? Schedule::query()->with('location')->find($validated['schedule_id'])
                : null;
            $result = $attendance->recordRecognition(null, $payload, $selectedSchedule);
            $record = $result['attendance'];
            $record?->loadMissing(['schedule', 'location']);
            $person = $result['event']->person;
            $person?->loadMissing('department');

            if ($record && $person && ! $result['duplicate']) {
                $emails->send($person, $record, $result['action']);
            }

            return [
                'message' => $result['duplicate'] ? 'Event was already processed.' : 'Recognition event processed.',
                'data' => [
                    'event_uuid' => $result['event']->uuid,
                    'result' => $result['event']->result,
                    'action' => $result['action'],
                    'duplicate' => $result['duplicate'],
                    'message' => $record ? null : $result['event']->failure_reason,
                    'person' => $person ? [
                        'id' => $person->id,
                        'institution_id' => $person->institution_id,
                        'full_name' => $person->full_name,
                        'type' => $person->type,
                        'email' => $person->email,
                        'phone' => $person->phone,
                        'department' => $person->department ? [
                            'code' => $person->department->code,
                            'name' => $person->department->name,
                        ] : null,
                        'program' => $person->program,
                        'year_level' => $person->year_level,
                        'position' => $person->position,
                        'status' => $person->status,
                        'joined_on' => $person->joined_on?->toDateString(),
                    ] : null,
                    'attendance' => $record ? [
                        'uuid' => $record->uuid,
                        'date' => $record->attendance_date->toDateString(),
                        'status' => $record->status,
                        'time_in' => $record->time_in?->toIso8601String(),
                        'time_out' => $record->time_out?->toIso8601String(),
                        'session' => $record->schedule ? [
                            'code' => $record->schedule->code,
                            'name' => $record->schedule->name,
                            'room' => $record->location?->name ?? $record->schedule->room_display,
                        ] : null,
                    ] : null,
                    'recognition' => $recognition['recognition'] ?? null,
                ],
            ];
        });
    }

    public function preview(Request $request, PythonRecognitionClient $python): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        return $this->respond(fn () => $python->preview($validated['image']));
    }

    public function enroll(Request $request, PythonRecognitionClient $python): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_without:images'],
            'images' => ['nullable', 'array', 'min:1', 'max:30', 'required_without:image'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'institution_id' => ['required', 'string', 'exists:people,institution_id'],
            'consent' => ['accepted'],
            'retention_until' => ['nullable', 'date', 'after:today'],
        ]);

        return $this->respond(function () use ($python, $validated, $request): array {
            $images = ! empty($validated['images'] ?? []) ? $validated['images'] : [$validated['image']];
            $extraction = count($images) > 1 ? $python->extractBatch($images) : $python->extract($images[0]);
            $samples = data_get($extraction, 'data.samples');
            if (! is_array($samples)) {
                $samples = [[
                    'embedding' => data_get($extraction, 'data.embedding'),
                    'model' => data_get($extraction, 'data.model'),
                    'dimensions' => data_get($extraction, 'data.dimensions'),
                    'quality' => data_get($extraction, 'data.quality'),
                    'detection_score' => data_get($extraction, 'data.detection_score'),
                ]];
            }
            if (count($samples) === 0) {
                throw new PythonRecognitionException(
                    'No usable face was found in the capture. Center one face in the camera and try again.',
                    422,
                    $extraction,
                );
            }

            $person = Person::query()
                ->with('user')
                ->where('institution_id', $validated['institution_id'])
                ->firstOrFail();

            $sessionUuid = (string) Str::uuid();
            $profiles = DB::transaction(function () use ($person, $samples, $images, $validated, $request, $sessionUuid): array {
                FacialProfile::query()->where('person_id', $person->id)->where('is_active', true)
                    ->get()->each->update(['is_active' => false]);
                $nextVersion = (int) FacialProfile::query()->where('person_id', $person->id)->max('version') + 1;
                return collect($samples)->map(function (array $sample, int $sampleIndex) use ($person, $nextVersion, $validated, $request, $sessionUuid, $images): FacialProfile {
                    $embedding = $sample['embedding'] ?? null;
                    $model = $sample['model'] ?? null;
                    if (! is_array($embedding) || count($embedding) < 16 || ! is_string($model) || $model === '') {
                        throw new PythonRecognitionException('Python returned an invalid facial enrollment sample.', 502, $sample);
                    }
                    $normalized = array_map('floatval', $embedding);
                    $checksum = hash('sha256', json_encode($normalized, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
                    $imagePath = null;
                    $frameIndex = (int) ($sample['frame'] ?? $sampleIndex);
                    if (config('attendpro.recognition.retain_enrollment_images') && isset($images[$frameIndex])) {
                        $imagePath = $images[$frameIndex]->storeAs(
                            "facial-enrollments/{$person->id}/{$sessionUuid}",
                            sprintf('sample-%02d.jpg', $sampleIndex + 1),
                            ['disk' => 'local'],
                        );
                    }
                    return FacialProfile::query()->create([
                        'person_id' => $person->id,
                        'embedding' => $normalized,
                        'embedding_checksum' => $checksum,
                        'model' => $model,
                        'dimensions' => count($normalized),
                        'version' => $nextVersion,
                        'enrollment_session_uuid' => $sessionUuid,
                        'consented_at' => now(),
                        'retention_until' => $validated['retention_until'] ?? null,
                        'quality_metadata' => [
                            'detection_score' => $sample['detection_score'] ?? null,
                            'quality' => $sample['quality'] ?? null,
                        ],
                        'sample_image_path' => $imagePath,
                        'enrolled_by' => $request->user()->id,
                        'is_active' => true,
                    ]);
                })->all();
            });

            if ($person->user) {
                $person->user->update(['is_active' => $person->status === 'active']);
            }

            $indexResult = $python->indexProfiles(collect($profiles)->map(fn (FacialProfile $profile) => [
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

            $firstProfile = $profiles[0];

            return [
                'message' => count($profiles).' high-quality facial samples enrolled.',
                'data' => [
                    'profile' => [
                        'profile_id' => $firstProfile->id,
                        'person_id' => $person->id,
                        'institution_id' => $person->institution_id,
                        'version' => $firstProfile->version,
                        'samples' => count($profiles),
                        'enrollment_session_uuid' => $sessionUuid,
                    ],
                    'rejected_samples' => data_get($extraction, 'data.rejected', []),
                    'sync' => $indexResult['data'] ?? $indexResult,
                ],
            ];
        });
    }

    private function respond(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation());
        } catch (PythonRecognitionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->details,
            ], $exception->statusCode);
        }
    }

}
