<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        [$records, $filters] = $this->filtered($request);

        return view('attendance.index', [
            'records' => $records->latest('attendance_date')->latest('time_in')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'people' => Person::query()->where('status', 'active')->orderBy('last_name')->limit(500)->get(),
        ]);
    }

    public function sessions(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $grouped = AttendanceRecord::query()
            ->selectRaw('schedule_id, attendance_date, location_id, COUNT(*) as total, '.
                "SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count, ".
                "SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count, ".
                "SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count, ".
                "SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('location_id', $id))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->whereHas('person', fn ($person) => $person->where('department_id', $id)))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereHas('schedule', fn ($schedule) => $schedule
                        ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('room_code', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($location) => $location->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->groupBy(['schedule_id', 'attendance_date', 'location_id'])
            ->orderByDesc('attendance_date')
            ->paginate(15)
            ->withQueryString();

        $scheduleIds = $grouped->getCollection()->pluck('schedule_id')->filter()->unique()->values();
        $schedules = Schedule::query()->whereIn('id', $scheduleIds)->with(['department', 'location'])->get()->keyBy('id');
        $locationIds = $grouped->getCollection()->pluck('location_id')->filter()->unique()->values();
        $fallbackLocations = Location::query()->whereIn('id', $locationIds)->get()->keyBy('id');

        $sessions = $grouped->getCollection()->map(function ($row) use ($schedules, $fallbackLocations) {
            $schedule = $row->schedule_id ? $schedules->get($row->schedule_id) : null;
            $location = $schedule?->location ?? ($row->location_id ? $fallbackLocations->get($row->location_id) : null);
            $date = \Carbon\Carbon::parse($row->attendance_date);

            if ($schedule && ! $schedule->is_active) {
                $status = 'Cancelled';
            } elseif ($date->isToday() || $date->isFuture()) {
                $status = 'Active';
            } else {
                $status = 'Completed';
            }

            return (object) [
                'key' => ($row->schedule_id ?? 'manual').'|'.$date->toDateString().'|'.($row->location_id ?? 'any'),
                'session_id' => $schedule ? 'SES-'.$schedule->code.'-'.$date->format('Ymd') : 'SES-MANUAL-'.$date->format('Ymd'),
                'schedule' => $schedule,
                'subject' => $schedule?->name ?? 'Manual attendance',
                'code' => $schedule?->code ?? 'MANUAL',
                'teacher' => $schedule && $schedule->department ? $schedule->department->name.' · '.($schedule->person_type ? ucfirst($schedule->person_type) : 'All roles') : '—',
                'date' => $date,
                'starts_at' => $schedule?->starts_at,
                'ends_at' => $schedule?->ends_at,
                'location' => $location,
                'total' => (int) $row->total,
                'present' => (int) $row->present_count,
                'late' => (int) $row->late_count,
                'absent' => (int) $row->absent_count,
                'status' => $status,
            ];
        });

        $sessions = new LengthAwarePaginator(
            $sessions,
            $grouped->total(),
            $grouped->perPage(),
            $grouped->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('attendance.sessions', [
            'sessions' => $sessions,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
        ]);
    }

    public function showSession(Request $request, string $schedule, string $date): View
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'exists:locations,id'],
            'status' => ['nullable', Rule::in(AttendanceRecord::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $validated['attendance_date'] = $date;
        if (! \Carbon\Carbon::hasFormat($date, 'Y-m-d')) {
            abort(404, 'Unknown attendance session.');
        }

        $scheduleModel = $schedule === 'manual' ? null : Schedule::query()->with(['department', 'location'])->findOrFail($schedule);

        $records = AttendanceRecord::query()->with(['person.department', 'location', 'terminal', 'schedule'])
            ->whereDate('attendance_date', $date)
            ->when($scheduleModel, fn ($query) => $query->where('schedule_id', $scheduleModel->id), fn ($query) => $query->whereNull('schedule_id'))
            ->when($validated['location_id'] ?? null, fn ($query, $id) => $query->where('location_id', $id))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->whereHas('person', fn ($person) => $person
                ->where('institution_id', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")))
            ->latest('time_in')
            ->paginate(25)
            ->withQueryString();

        if ($records->total() === 0 && empty($validated['location_id']) && empty($validated['status']) && empty($validated['search'])) {
            abort(404, 'No attendance records found for this session.');
        }

        return view('attendance.session-show', [
            'schedule' => $scheduleModel,
            'scheduleKey' => $schedule,
            'date' => \Carbon\Carbon::parse($date),
            'records' => $records,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => ['required', 'exists:people,id'],
            'attendance_date' => ['required', 'date'],
            'time_in' => ['nullable', 'date'],
            'time_out' => ['nullable', 'date', 'after_or_equal:time_in'],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'location_id' => ['nullable', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AttendanceRecord::query()->create($validated + [
            'uuid' => (string) Str::uuid(),
            'source' => 'manual',
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Manual attendance record added.');
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $validated = $request->validate([
            'time_in' => ['nullable', 'date'],
            'time_out' => ['nullable', 'date', 'after_or_equal:time_in'],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $attendanceRecord->update($validated + [
            'corrected_by' => $request->user()->id,
            'corrected_at' => now(),
            'revision' => $attendanceRecord->revision + 1,
        ]);

        return back()->with('success', 'Attendance correction saved and audited.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$records] = $this->filtered($request);
        $filename = 'attendpro-attendance-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($records): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Date', 'Institution ID', 'Name', 'Role', 'Department', 'Status', 'Time In', 'Time Out', 'Location', 'Source', 'Notes']);
            $records->orderBy('attendance_date')->chunk(500, function ($chunk) use ($stream): void {
                foreach ($chunk as $record) {
                    fputcsv($stream, [
                        $record->attendance_date->toDateString(), $record->person->institution_id,
                        $record->person->full_name, ucfirst($record->person->type), $record->person->department?->name,
                        ucfirst($record->status), $record->time_in?->format('Y-m-d H:i:s'), $record->time_out?->format('Y-m-d H:i:s'),
                        $record->schedule?->room_display ?? $record->location?->name, ucfirst($record->source), $record->notes,                    ]);
                }
            });
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(Person::DIRECTORY_TYPES)], 'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'], 'status' => ['nullable', Rule::in(AttendanceRecord::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $records = AttendanceRecord::query()->with(['person.department', 'location', 'terminal', 'schedule'])
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->when($filters['location_id'] ?? null, fn ($query, $id) => $query->where('location_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('person', fn ($person) => $person->where('type', $type)))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->whereHas('person', fn ($person) => $person->where('department_id', $id)))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('person', fn ($person) => $person
                ->where('institution_id', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")));

        return [$records, $filters];
    }
}
