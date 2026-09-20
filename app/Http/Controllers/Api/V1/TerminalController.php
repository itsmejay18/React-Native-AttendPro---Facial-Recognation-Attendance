<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_version' => ['nullable', 'string', 'max:60'],
            'capabilities' => ['nullable', 'array'],
        ]);

        $terminal = $request->attributes->get('terminal');
        if (array_key_exists('capabilities', $validated)) {
            $validated['capabilities'] = array_replace(
                $validated['capabilities'] ?? [],
                $terminal->capabilities ?? [],
            );
        }

        $terminal->timestamps = false;
        $terminal->updateQuietly($validated + ['last_seen_at' => now(), 'status' => 'online']);

        return response()->json([
            'data' => [
                'terminal_uuid' => $terminal->uuid,
                'status' => 'online',
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function configuration(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal')->load('location');
        $schedules = Schedule::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('location_id')->orWhere('location_id', $terminal->location_id))
            ->get([
                'id', 'code', 'name', 'person_type', 'department_id', 'location_id',
                'days_of_week', 'starts_at', 'ends_at', 'check_in_opens_at',
                'check_in_closes_at', 'grace_minutes', 'checkout_required',
                'effective_from', 'effective_until',
            ]);

        return response()->json(['data' => [
            'api_version' => 'v1',
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'minimum_confidence' => config('attendpro.recognition.minimum_confidence'),
            'minimum_margin' => config('attendpro.recognition.minimum_margin'),
            'terminal' => [
                'uuid' => $terminal->uuid,
                'code' => $terminal->code,
                'name' => $terminal->name,
                'location' => $terminal->location?->only(['id', 'code', 'name', 'timezone']),
                'capabilities' => $terminal->capabilities ?? [],
            ],
            'schedules' => $schedules,
        ]]);
    }
}
