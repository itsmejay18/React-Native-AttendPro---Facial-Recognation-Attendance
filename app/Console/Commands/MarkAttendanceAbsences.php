<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Person;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MarkAttendanceAbsences extends Command
{
    protected $signature = 'attendance:mark-absent {date? : Date in YYYY-MM-DD format}';

    protected $description = 'Create absent records for active people covered by schedules with no attendance record';

    public function handle(): int
    {
        $date = CarbonImmutable::parse(
            $this->argument('date') ?: CarbonImmutable::yesterday(config('app.timezone'))->toDateString(),
            config('app.timezone'),
        );
        $day = $date->dayOfWeekIso;
        $created = 0;

        Schedule::query()->where('is_active', true)->with('people')->get()
            ->filter(fn (Schedule $schedule) => in_array($day, array_map('intval', $schedule->days_of_week), true))
            ->filter(fn (Schedule $schedule) => (! $schedule->effective_from || $schedule->effective_from->lte($date))
                && (! $schedule->effective_until || $schedule->effective_until->gte($date)))
            ->each(function (Schedule $schedule) use ($date, &$created): void {
                $people = $schedule->people->isNotEmpty()
                    ? $schedule->people->where('status', 'active')
                    : Person::query()->where('status', 'active')
                        ->when($schedule->person_type, fn ($query, $type) => $query->where('type', $type))
                        ->when($schedule->department_id, fn ($query, $id) => $query->where('department_id', $id))
                        ->when($schedule->program, fn ($query, $program) => $query->where('program', $program))
                        ->get();

                foreach ($people as $person) {
                    $record = AttendanceRecord::query()->firstOrCreate([
                        'person_id' => $person->id,
                        'schedule_id' => $schedule->id,
                        'attendance_date' => $date->toDateString(),
                    ], [
                        'uuid' => (string) Str::uuid(),
                        'location_id' => $schedule->location_id,
                        'status' => 'absent',
                        'source' => 'system',
                    ]);
                    $created += $record->wasRecentlyCreated ? 1 : 0;
                }
            });

        $this->info("Created {$created} absent attendance record(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
