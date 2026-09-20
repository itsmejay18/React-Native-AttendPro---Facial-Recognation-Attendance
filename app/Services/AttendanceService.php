<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Models\Schedule;
use App\Models\Terminal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceService
{
    public function __construct(private readonly ScheduleResolver $schedules) {}

    /**
     * @return array{event: RecognitionEvent, attendance: AttendanceRecord|null, action: string, duplicate: bool}
     */
    public function recordRecognition(?Terminal $terminal, array $payload, ?Schedule $selectedSchedule = null): array
    {
        return DB::transaction(function () use ($terminal, $payload, $selectedSchedule): array {
            $existing = RecognitionEvent::query()
                ->when($terminal, fn ($query) => $query->where('terminal_id', $terminal->id), fn ($query) => $query->whereNull('terminal_id'))
                ->where('external_event_id', $payload['event_id'])
                ->with('attendanceRecord')
                ->first();

            if ($existing) {
                return [
                    'event' => $existing,
                    'attendance' => $existing->attendanceRecord,
                    'action' => 'duplicate',
                    'duplicate' => true,
                ];
            }

            $capturedAt = CarbonImmutable::parse($payload['captured_at'])->setTimezone(config('app.timezone'));
            $person = $this->resolvePerson($payload);
            $result = $payload['result'];

            if ($result === 'matched' && ! $person) {
                $result = 'failed';
                $payload['failure_reason'] = 'Matched identity was not found or is inactive.';
            }

            $event = RecognitionEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'external_event_id' => $payload['event_id'],
                'terminal_id' => $terminal?->id,
                'person_id' => $person?->id,
                'result' => $result,
                'confidence' => $payload['confidence'] ?? null,
                'direction' => $payload['direction'] ?? 'auto',
                'captured_at' => $capturedAt,
                'review_status' => $result === 'matched' ? 'not_required' : 'pending',
                'failure_reason' => $payload['failure_reason'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            if ($result !== 'matched' || ! $person) {
                return ['event' => $event, 'attendance' => null, 'action' => $result, 'duplicate' => false];
            }

            // Laravel resolves the applicable program and time schedule. Python
            // contributes identity only; it does not choose attendance rooms.
            if ($selectedSchedule && ! $this->schedules->isEligible($selectedSchedule, $person, $capturedAt, $terminal?->location_id)) {
                $event->update([
                    'review_status' => 'pending',
                    'failure_reason' => 'The selected attendance session is not active for this student, program, or scan time.',
                ]);

                return ['event' => $event, 'attendance' => null, 'action' => 'session_unavailable', 'duplicate' => false];
            }

            $schedule = $selectedSchedule ?? $this->schedules->resolve($person, $capturedAt, $terminal?->location_id);

            if (! $schedule && ! $terminal) {
                $event->update([
                    'review_status' => 'pending',
                    'failure_reason' => 'No active attendance schedule matches this student, program, and scan time.',
                ]);

                return ['event' => $event, 'attendance' => null, 'action' => 'outside_schedule', 'duplicate' => false];
            }

            // Room/session guard (server-side only: the room is taken from the
            // resolved schedule and the authenticated terminal's location, never
            // from any client-supplied value). "Any Location" skips this check.
            $roomMessage = $terminal && $schedule ? $this->roomMismatchMessage($schedule, $terminal) : null;

            if ($roomMessage !== null) {
                $event->update([
                    'review_status' => 'pending',
                    'failure_reason' => $roomMessage,
                ]);

                return ['event' => $event, 'attendance' => null, 'action' => 'wrong_location', 'duplicate' => false];
            }

            $record = AttendanceRecord::query()
                ->where('person_id', $person->id)
                ->where('attendance_date', $capturedAt->toDateString())
                ->when($schedule, fn ($query) => $query->where('schedule_id', $schedule->id))
                ->when(! $schedule, fn ($query) => $query->whereNull('schedule_id'))
                ->lockForUpdate()
                ->first();

            $direction = $payload['direction'] ?? 'auto';
            $action = 'time_in';

            if (! $record && $direction === 'time_out') {
                $event->update([
                    'review_status' => 'pending',
                    'failure_reason' => 'A time-out was received without an existing time-in record.',
                ]);

                return ['event' => $event, 'attendance' => null, 'action' => 'missing_time_in', 'duplicate' => false];
            }

            if (! $record && $schedule && ! $this->isInsideCheckInWindow($schedule, $capturedAt)) {
                $event->update([
                    'review_status' => 'pending',
                    'failure_reason' => 'Recognition occurred outside the configured check-in window.',
                ]);

                return ['event' => $event, 'attendance' => null, 'action' => 'outside_window', 'duplicate' => false];
            }

            if ($record && ! $record->time_in) {
                $record->update([
                    'time_in' => $capturedAt,
                    'status' => $this->arrivalStatus($schedule, $capturedAt),
                    'source' => 'recognition',
                    'terminal_id' => $terminal?->id,
                    'location_id' => $schedule?->location_id ?? $terminal?->location_id,
                    'match_confidence' => $payload['confidence'] ?? null,
                    'revision' => $record->revision + 1,
                ]);
            } elseif (! $record) {
                $record = AttendanceRecord::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'person_id' => $person->id,
                    'schedule_id' => $schedule?->id,
                    'location_id' => $schedule?->location_id ?? $terminal?->location_id,
                    'terminal_id' => $terminal?->id,
                    'attendance_date' => $capturedAt->toDateString(),
                    'time_in' => $capturedAt,
                    'status' => $this->arrivalStatus($schedule, $capturedAt),
                    'source' => 'recognition',
                    'match_confidence' => $payload['confidence'] ?? null,
                ]);
            } elseif ($direction === 'time_in'
                || ($direction === 'auto' && $record->time_in?->diffInSeconds($capturedAt) < config('attendpro.recognition.duplicate_scan_seconds'))) {
                $action = 'already_timed_in';
            } elseif (($schedule && ! $schedule->checkout_required) || ($record->time_out && $direction === 'auto')) {
                $action = 'already_complete';
            } else {
                $record->update([
                    'time_out' => $capturedAt,
                    'terminal_id' => $terminal?->id,
                    'location_id' => $schedule?->location_id ?? $terminal?->location_id,
                    'match_confidence' => max($record->match_confidence ?? 0, $payload['confidence'] ?? 0),
                    'revision' => $record->revision + 1,
                ]);
                $action = 'time_out';
            }

            $event->update(['attendance_record_id' => $record->id]);

            return ['event' => $event, 'attendance' => $record->fresh(), 'action' => $action, 'duplicate' => false];
        }, 3);
    }

    /**
     * Return a rejection message when the trusted terminal location does not
     * correspond to the schedule's assigned room/location, or null when the
     * scan may proceed. "Any Location" schedules always return null.
     */
    private function roomMismatchMessage(Schedule $schedule, Terminal $terminal): ?string
    {
        if ($schedule->isAnyLocation()) {
            return null;
        }

        if ($schedule->location_id !== null && (int) $terminal->location_id === (int) $schedule->location_id) {
            return null;
        }

        if ($schedule->location_id !== null) {
            return sprintf(
                'Attendance cannot be recorded. This attendance session is assigned to %s.',
                $schedule->room_display,
            );
        }

        // Defensive fallback for room-only schedules created outside the
        // normal provisioning path: match against the terminal's location.
        $room = trim((string) ($schedule->room_code ?? ''));

        if ($room !== '') {
            $location = $terminal->location;

            if ($location) {
                foreach ([$location->code, $location->name] as $candidate) {
                    if (is_string($candidate) && strcasecmp(trim($candidate), $room) === 0) {
                        return null;
                    }
                }

                if (stripos($location->name, $room) !== false) {
                    return null;
                }
            }

            return sprintf(
                'Attendance cannot be recorded. This attendance session is assigned to %s.',
                $schedule->room_display,
            );
        }

        return null;
    }

    private function resolvePerson(array $payload): ?Person
    {
        return Person::query()
            ->where('status', 'active')
            ->when(
                isset($payload['person_id']),
                fn ($query) => $query->whereKey($payload['person_id']),
                fn ($query) => $query->where('institution_id', $payload['institution_id'] ?? ''),
            )
            ->first();
    }

    private function arrivalStatus($schedule, CarbonImmutable $capturedAt): string
    {
        if (! $schedule) {
            return 'present';
        }

        $lateAfter = CarbonImmutable::parse(
            $capturedAt->toDateString().' '.$schedule->starts_at,
            config('app.timezone'),
        )->addMinutes($schedule->grace_minutes);

        return $capturedAt->greaterThan($lateAfter) ? 'late' : 'present';
    }

    private function isInsideCheckInWindow($schedule, CarbonImmutable $capturedAt): bool
    {
        $date = $capturedAt->toDateString();
        $opens = $schedule->check_in_opens_at
            ? CarbonImmutable::parse($date.' '.$schedule->check_in_opens_at, config('app.timezone'))
            : null;
        $closes = $schedule->check_in_closes_at
            ? CarbonImmutable::parse($date.' '.$schedule->check_in_closes_at, config('app.timezone'))
            : null;

        return (! $opens || $capturedAt->greaterThanOrEqualTo($opens))
            && (! $closes || $capturedAt->lessThanOrEqualTo($closes));
    }
}
