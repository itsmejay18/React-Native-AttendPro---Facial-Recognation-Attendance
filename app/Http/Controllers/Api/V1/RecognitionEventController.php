<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AttendanceEmailNotifier;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecognitionEventController extends Controller
{
    public function store(
        Request $request,
        AttendanceService $attendance,
        AttendanceEmailNotifier $emails,
    ): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string', 'max:100'],
            'result' => ['required', Rule::in(['matched', 'unknown', 'failed'])],
            'person_id' => ['nullable', 'integer', 'exists:people,id', Rule::requiredIf($request->input('result') === 'matched' && ! $request->filled('institution_id'))],
            'institution_id' => ['nullable', 'string', 'max:60', Rule::requiredIf($request->input('result') === 'matched' && ! $request->filled('person_id'))],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'direction' => ['nullable', Rule::in(['auto', 'time_in', 'time_out'])],
            'captured_at' => ['required', 'date'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validated['result'] !== 'matched') {
            unset($validated['person_id'], $validated['institution_id']);
        }

        if ($validated['result'] === 'matched'
            && ($validated['confidence'] ?? 0) < config('attendpro.recognition.minimum_confidence')) {
            $validated['result'] = 'failed';
            $validated['failure_reason'] = 'Match confidence is below the server threshold.';
            unset($validated['person_id'], $validated['institution_id']);
        }

        $result = $attendance->recordRecognition(null, $validated);
        $record = $result['attendance'];
        $person = $result['event']->person;
        $person?->loadMissing('department');

        if ($record && $person && ! $result['duplicate']) {
            $emails->send($person, $record, $result['action']);
        }

        return response()->json([
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
                ] : null,
            ],
        ], $result['duplicate'] ? 200 : 201);
    }
}
