<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FacialProfile;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaceProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updated_since' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $profiles = FacialProfile::query()
            ->with(['person:id,institution_id,type,first_name,middle_name,last_name,suffix,status'])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('retention_until')->orWhereDate('retention_until', '>=', today()))
            ->whereHas('person', fn ($query) => $query->where('status', 'active'))
            ->when($validated['updated_since'] ?? null, fn ($query, $since) => $query->where('updated_at', '>', $since))
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? config('attendpro.recognition.face_sync_page_size'));

        return response()->json([
            'data' => $profiles->getCollection()->map(fn (FacialProfile $profile) => [
                'profile_id' => $profile->id,
                'person_id' => $profile->person_id,
                'institution_id' => $profile->person->institution_id,
                'person_type' => $profile->person->type,
                'full_name' => $profile->person->full_name,
                'model' => $profile->model,
                'dimensions' => $profile->dimensions,
                'version' => $profile->version,
                'embedding' => $profile->embedding,
                'checksum' => $profile->embedding_checksum,
                'updated_at' => $profile->updated_at->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'string', 'exists:people,institution_id'],
            'embedding' => ['required', 'array', 'min:16', 'max:4096'],
            'embedding.*' => ['required', 'numeric', 'between:-10,10'],
            'model' => ['required', 'string', 'max:100'],
            'consented_at' => ['required', 'date'],
            'retention_until' => ['nullable', 'date', 'after:consented_at'],
            'enrolled_by' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $person = Person::query()->where('institution_id', $validated['institution_id'])->firstOrFail();
        $normalized = array_map('floatval', $validated['embedding']);
        $checksum = hash('sha256', json_encode($normalized, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

        $profile = DB::transaction(function () use ($validated, $person, $normalized, $checksum) {
            FacialProfile::query()->where('person_id', $person->id)->where('is_active', true)
                ->get()->each->update(['is_active' => false]);
            $nextVersion = (int) FacialProfile::query()->where('person_id', $person->id)->max('version') + 1;

            return FacialProfile::query()->create([
                'person_id' => $person->id,
                'embedding' => $normalized,
                'embedding_checksum' => $checksum,
                'model' => $validated['model'],
                'dimensions' => count($normalized),
                'version' => $nextVersion,
                'consented_at' => $validated['consented_at'],
                'retention_until' => $validated['retention_until'] ?? null,
                'enrolled_by' => $validated['enrolled_by'] ?? null,
                'is_active' => true,
            ]);
        });

        return response()->json(['message' => 'Facial profile enrolled.', 'data' => [
            'profile_id' => $profile->id,
            'person_id' => $person->id,
            'institution_id' => $person->institution_id,
            'version' => $profile->version,
            'checksum' => $checksum,
        ]], 201);
    }
}
