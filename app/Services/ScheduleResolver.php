<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ScheduleResolver
{
    public function isEligible(Schedule $schedule, Person $person, CarbonInterface $capturedAt, ?int $locationId = null): bool
    {
        $date = $capturedAt->toDateString();

        if (! $schedule->is_active
            || ($schedule->person_type !== null && $schedule->person_type !== $person->type)
            || ($schedule->department_id !== null && (int) $schedule->department_id !== (int) $person->department_id)
            || ($schedule->program !== null && $schedule->program !== $person->program)
            || ($locationId !== null && $schedule->location_id !== null && (int) $schedule->location_id !== $locationId)
            || ($schedule->effective_from && $schedule->effective_from->isAfter($date))
            || ($schedule->effective_until && $schedule->effective_until->isBefore($date))
            || ! $this->isActiveAt($schedule, $capturedAt, $capturedAt->dayOfWeekIso)) {
            return false;
        }

        if (! $schedule->people()->exists()) {
            return true;
        }

        return $schedule->people()
            ->where(fn ($query) => $query->whereNull('schedule_assignments.effective_from')->orWhere('schedule_assignments.effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('schedule_assignments.effective_until')->orWhere('schedule_assignments.effective_until', '>=', $date))
            ->whereKey($person->id)
            ->exists();
    }

    public function resolve(Person $person, CarbonInterface $capturedAt, ?int $locationId = null): ?Schedule
    {
        $date = $capturedAt->toDateString();
        $day = $capturedAt->dayOfWeekIso;

        $assigned = $person->schedules()
            ->where('schedules.is_active', true)
            ->where(fn ($query) => $query->whereNull('schedules.effective_from')->orWhere('schedules.effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('schedules.effective_until')->orWhere('schedules.effective_until', '>=', $date))
            ->where(fn ($query) => $query->whereNull('schedule_assignments.effective_from')->orWhere('schedule_assignments.effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('schedule_assignments.effective_until')->orWhere('schedule_assignments.effective_until', '>=', $date))
            ->get()
            ->first(fn (Schedule $schedule) => $this->isActiveAt($schedule, $capturedAt, $day));

        if ($assigned) {
            return $assigned;
        }

        return Schedule::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('person_type')->orWhere('person_type', $person->type))
            ->where(fn ($query) => $query->whereNull('department_id')->orWhere('department_id', $person->department_id))
            ->where(fn ($query) => $query->whereNull('program')->orWhere('program', $person->program))
            ->when($locationId, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('location_id')->orWhere('location_id', $locationId)))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->get()
            ->filter(fn (Schedule $schedule) => $this->isActiveAt($schedule, $capturedAt, $day))
            ->sortByDesc(fn (Schedule $schedule) => (int) ($schedule->program !== null) + (int) ($schedule->department_id !== null) + (int) ($schedule->person_type !== null))
            ->first();
    }

    /**
     * Laravel owns schedule eligibility. A Python match may be recorded only
     * inside the class/check-in time window for the student's schedule.
     */
    private function isActiveAt(Schedule $schedule, CarbonInterface $capturedAt, int $day): bool
    {
        if (! in_array($day, array_map('intval', $schedule->days_of_week), true)) {
            return false;
        }

        $date = $capturedAt->toDateString();
        $timezone = config('app.timezone');
        $opens = CarbonImmutable::parse($date.' '.($schedule->check_in_opens_at ?? $schedule->starts_at), $timezone);
        $closes = CarbonImmutable::parse($date.' '.($schedule->check_in_closes_at ?? $schedule->ends_at), $timezone);

        if ($closes->lessThan($opens)) {
            $closes = $closes->addDay();
            if ($capturedAt->lessThan($opens)) {
                $capturedAt = $capturedAt->addDay();
            }
        }

        return $capturedAt->greaterThanOrEqualTo($opens)
            && $capturedAt->lessThanOrEqualTo($closes);
    }
}
