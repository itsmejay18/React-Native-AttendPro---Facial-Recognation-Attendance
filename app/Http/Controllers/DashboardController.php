<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Person;
use App\Models\RecognitionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        if ($request->user()->hasAnyRole(Person::TYPES)) {
            $person = $request->user()->person()
                ->with(['department', 'activeFacialProfiles'])
                ->first();
            $recentAttendance = $person?->attendanceRecords()
                ->with(['schedule', 'location'])
                ->latest('attendance_date')
                ->latest('time_in')
                ->limit(8)
                ->get() ?? collect();

            return view('portal.dashboard', [
                'person' => $person,
                'todayRecord' => $recentAttendance->first(fn (AttendanceRecord $record): bool => $record->attendance_date->isToday()),
                'recentAttendance' => $recentAttendance,
                'monthRecorded' => $person?->attendanceRecords()
                    ->whereBetween('attendance_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count() ?? 0,
            ]);
        }

        $today = today();
        $peopleCounts = Person::query()->where('status', 'active')->whereIn('type', Person::DIRECTORY_TYPES)
            ->selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type');
        $attendanceCounts = AttendanceRecord::query()->whereDate('attendance_date', $today)
            ->whereHas('person', fn ($query) => $query->whereIn('type', Person::DIRECTORY_TYPES))
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $activePeople = max(1, (int) $peopleCounts->sum());
        $recorded = (int) $attendanceCounts->sum();

        $months = collect(range(7, 0))->map(fn (int $monthsAgo) => CarbonImmutable::now()->subMonths($monthsAgo));
        $trend = $months->map(function (CarbonImmutable $month) use ($activePeople): int {
            $averageDailyAttendance = AttendanceRecord::query()
                ->whereBetween('attendance_date', [$month->startOfMonth(), $month->endOfMonth()])
                ->whereHas('person', fn ($query) => $query->whereIn('type', Person::DIRECTORY_TYPES))
                ->count() / max(1, $month->daysInMonth);

            return min(100, (int) round(($averageDailyAttendance / $activePeople) * 100));
        });

        return view('dashboard', [
            'peopleCounts' => $peopleCounts,
            'attendanceCounts' => $attendanceCounts,
            'attendanceRate' => (int) round(($recorded / $activePeople) * 100),
            'trendLabels' => $months->map(fn (CarbonImmutable $month) => $month->format('M')),
            'trendValues' => $trend,
            'pendingExceptions' => RecognitionEvent::query()->where('review_status', 'pending')->count(),
        ]);
    }
}
