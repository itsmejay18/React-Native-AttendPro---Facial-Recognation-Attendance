<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        return view('schedules.index', [
            'schedules' => Schedule::query()->with(['department', 'location'])->withCount('people')->orderBy('name')->paginate(20),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'locationTypes' => Schedule::LOCATION_TYPES,
            'roomTypes' => Schedule::ROOM_TYPES,
            'programs' => Person::PROGRAMS,
            'roomSuggestions' => collect(Schedule::ROOM_SUGGESTIONS)
                ->merge(Schedule::query()->whereNotNull('room_code')->distinct()->orderBy('room_code')->limit(50)->pluck('room_code'))
                ->filter()
                ->unique()
                ->values(),
            'people' => Person::query()->where('status', 'active')->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Schedule::query()->create($this->validated($request));

        return back()->with('success', 'Schedule created.');
    }    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $schedule->update($this->validated($request, $schedule));

        return back()->with('success', 'Schedule updated.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->update(['is_active' => false]);

        return back()->with('success', 'Schedule deactivated.');
    }

    public function assign(Request $request, Schedule $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'person_ids' => ['present', 'array'],
            'person_ids.*' => ['integer', 'exists:people,id'],
        ]);

        $schedule->people()->sync($validated['person_ids']);

        return back()->with('success', 'Schedule assignments updated.');
    }

    /**
     * Previously used room codes for autocomplete suggestions.
     * Teachers can still enter a completely new room; nothing is hard-coded.
     */
    public function suggestRooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        $search = str_replace(['%', '_'], '', (string) ($validated['q'] ?? ''));
        $query = Schedule::query()->whereNotNull('room_code');
        if ($search !== '') $query->where('room_code', 'like', '%'.$search.'%');

        $storedRooms = $query->distinct()->orderBy('room_code')->limit(50)->pluck('room_code');
        $allRooms = collect(Schedule::ROOM_SUGGESTIONS)->merge($storedRooms)->filter();
        if ($search !== '') {
            $allRooms = $allRooms->filter(fn (string $room) => str_contains(mb_strtolower($room), mb_strtolower($search)));
        }

        return response()->json([
            'data' => $allRooms->unique()->take(10)->values(),
        ]);
    }

    private function validated(Request $request, ?Schedule $schedule = null): array
    {
        $locationType = (string) $request->input('location_type', $schedule?->location_type ?? Schedule::LOCATION_TYPE_ANY);
        $requiresRoom = in_array($locationType, Schedule::ROOM_TYPES, true);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('schedules')->ignore($schedule)],
            'name' => ['required', 'string', 'max:255'],
            'person_type' => ['nullable', Rule::in(Person::TYPES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'program' => ['nullable', Rule::in(Person::PROGRAMS)],
            'location_type' => ['required', Rule::in(array_keys(Schedule::LOCATION_TYPES))],
            'location_id' => [
                'nullable', 'exists:locations,id',
                Rule::requiredIf($locationType === Schedule::LOCATION_TYPE_MAIN_ENTRANCE),
                Rule::prohibitedIf($locationType === Schedule::LOCATION_TYPE_ANY || $requiresRoom),
            ],
            'room_code' => [
                'nullable', 'string', 'max:60',
                Rule::requiredIf($requiresRoom),
                Rule::prohibitedIf(! $requiresRoom),
            ],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'check_in_opens_at' => ['nullable', 'date_format:H:i'],
            'check_in_closes_at' => ['nullable', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'between:0,180'],
            'checkout_required' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['checkout_required'] = $request->boolean('checkout_required');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['days_of_week'] = array_values(array_unique(array_map('intval', $validated['days_of_week'])));

        if (isset($validated['room_code'])) {
            $room = preg_replace('/\s+/', ' ', trim($validated['room_code'])) ?? '';
            $validated['room_code'] = $room === '' ? null : substr($room, 0, 60);
        }

        if ($locationType === Schedule::LOCATION_TYPE_ANY) {
            $validated['location_id'] = null;
            $validated['room_code'] = null;
        } elseif ($locationType === Schedule::LOCATION_TYPE_MAIN_ENTRANCE) {
            $validated['room_code'] = null;
        } elseif ($requiresRoom && ($validated['room_code'] ?? null) !== null) {
            // Reuse the existing locations structure: materialize the typed room
            // on demand so sessions, terminals, and records keep working through
            // location_id. The teacher never pre-registers anything.
            $pending = $schedule ?? new Schedule;
            $pending->syncRoomLocation($locationType, $validated['room_code']);
            $validated['location_id'] = $pending->location_id;
            $validated['room_code'] = $pending->room_code;
        }

        return $validated;
    }
}
