<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Location;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'preset' => ['nullable', Rule::in(['today', 'week', 'month'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'status' => ['nullable', Rule::in(AttendanceRecord::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'status'])],
        ]);

        [$from, $to, $activePreset] = $this->resolveRange($validated);
        $filters = array_merge($validated, ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        $base = fn () => AttendanceRecord::query()
            ->whereBetween('attendance_records.attendance_date', [$from, $to])
            ->when($validated['department_id'] ?? null, fn ($query, $id) => $query->whereHas('person', fn ($person) => $person->where('department_id', $id)))
            ->when($validated['location_id'] ?? null, fn ($query, $id) => $query->where('attendance_records.location_id', $id))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('attendance_records.status', $status))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->whereHas('person', fn ($person) => $person
                ->where('institution_id', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")));

        $totals = (clone $base())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $totals->sum();
        $attendanceRate = $total > 0
            ? (int) round(((int) ($totals['present'] ?? 0) + (int) ($totals['late'] ?? 0)) / $total * 100)
            : 0;

        $byRole = (clone $base())
            ->join('people', 'people.id', '=', 'attendance_records.person_id')
            ->selectRaw('people.type as role, COUNT(*) as aggregate')
            ->groupBy('people.type')
            ->pluck('aggregate', 'role');

        $byDepartment = (clone $base())
            ->join('people', 'people.id', '=', 'attendance_records.person_id')
            ->leftJoin('departments', 'departments.id', '=', 'people.department_id')
            ->selectRaw("COALESCE(departments.name, 'Unassigned') as department, COUNT(*) as aggregate")
            ->groupBy('department')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'department');

        $daily = (clone $base())
            ->selectRaw('attendance_date as date, COUNT(*) as aggregate')
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->pluck('aggregate', 'date');

        $sort = $validated['sort'] ?? 'newest';
        $records = (clone $base())->with(['person.department', 'schedule', 'location', 'terminal'])
            ->when($sort === 'oldest', fn ($query) => $query->oldest('attendance_date')->oldest('time_in'))
            ->when($sort === 'name', fn ($query) => $query
                ->join('people as sort_people', 'sort_people.id', '=', 'attendance_records.person_id')
                ->orderBy('sort_people.last_name')->orderBy('sort_people.first_name')
                ->select('attendance_records.*'))
            ->when($sort === 'status', fn ($query) => $query->orderBy('attendance_records.status')->latest('attendance_records.attendance_date'))
            ->when(! in_array($sort, ['oldest', 'name', 'status'], true), fn ($query) => $query->latest('attendance_date')->latest('time_in'))
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', [
            'from' => $from, 'to' => $to,
            'activePreset' => $activePreset,
            'filters' => $filters,
            'total' => $total,
            'totals' => $totals,
            'attendanceRate' => $attendanceRate,
            'byRole' => $byRole,
            'byDepartment' => $byDepartment,
            'daily' => $daily,
            'records' => $records,
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'statuses' => AttendanceRecord::STATUSES,
            'personTypes' => Person::TYPES,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: ?string}
     */
    private function resolveRange(array $validated): array
    {
        $today = CarbonImmutable::today();

        if (($validated['preset'] ?? null) === 'today') {
            return [$today->startOfDay(), $today->endOfDay(), 'today'];
        }
        if (($validated['preset'] ?? null) === 'week') {
            return [$today->startOfWeek()->startOfDay(), $today->endOfDay(), 'week'];
        }
        if (($validated['preset'] ?? null) === 'month') {
            return [$today->startOfMonth()->startOfDay(), $today->endOfDay(), 'month'];
        }
        if (isset($validated['date_from']) || isset($validated['date_to'])) {
            $from = CarbonImmutable::parse($validated['date_from'] ?? $validated['date_to'])->startOfDay();
            $to = CarbonImmutable::parse($validated['date_to'] ?? $validated['date_from'])->endOfDay();

            return [$from, $to, null];
        }

        return [$today->subDays(29)->startOfDay(), $today->endOfDay(), null];
    }
}
