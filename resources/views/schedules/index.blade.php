@extends('layouts.dashboard')

@section('title', 'Schedules | AttendPro')
@section('page-title', 'Schedules & Policies')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <x-attendance-tabs />

        <section class="kit-dashboard-section-head">
            <div><h2>Attendance schedules</h2><p>Configure student attendance days, time windows, grace periods, programs, and rooms.</p></div>
            @hasanyrole('super_admin|attendance_admin')
                <div class="kit-action-row">
                    <button class="kit-button secondary" type="button" data-modal-open="#department-modal"><i class="ph ph-buildings"></i>Department</button>
                    <button class="kit-button secondary" type="button" data-modal-open="#location-modal"><i class="ph ph-map-pin"></i>Location</button>
                    <button class="kit-button primary" type="button" data-modal-open="#schedule-modal"><i class="ph ph-plus"></i>New schedule</button>
                </div>
            @endhasanyrole
        </section>

        <section class="kit-dashboard-panel">
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead><tr><th>Schedule</th><th>Coverage</th><th>Days</th><th>Hours</th><th>Policy</th><th>Assignments</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($schedules as $schedule)
                        <tr>
                            <td><strong>{{ $schedule->name }}</strong><br><span class="kit-muted">{{ $schedule->code }}</span></td>
                            <td>Students<br><span class="kit-muted">{{ $schedule->program ?? 'All programs' }} · {{ $schedule->room_display }}</span></td>
                            <td>{{ collect($schedule->days_of_week)->map(fn ($day) => ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$day - 1])->join(', ') }}</td>
                            <td>{{ \Carbon\Carbon::parse($schedule->starts_at)->format('h:i A') }}–{{ \Carbon\Carbon::parse($schedule->ends_at)->format('h:i A') }}</td>
                            <td>{{ $schedule->grace_minutes }} min grace<br><span class="kit-muted">{{ $schedule->checkout_required ? 'Time-out required' : 'Time-in only' }}</span></td>
                            <td>{{ $schedule->people_count }} direct</td>
                            <td><span class="kit-badge {{ $schedule->is_active ? 'success' : 'danger' }}">{{ $schedule->is_active ? 'Active' : 'Inactive' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="attendpro-empty">No schedules configured yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $schedules->links() }}</div>
        </section>

    </div>
@endsection

@hasanyrole('super_admin|attendance_admin')
    @push('modals')
        @php($failedModal = $errors->any() ? old('_modal') : null)

        <div class="kit-modal" id="schedule-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-title" data-modal-auto-open="{{ $failedModal === 'schedule-modal' ? 'true' : 'false' }}">
            <form class="kit-modal-panel attendpro-modal-wide attendpro-modal-scroll" method="POST" action="{{ route('schedules.store') }}">
                @csrf
                <input type="hidden" name="_modal" value="schedule-modal">
                <div class="kit-modal-head"><div><strong id="schedule-title">Create attendance schedule</strong><span class="attendpro-modal-subtitle">Set the coverage, schedule window, and attendance policy.</span></div><button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button></div>
                <div class="kit-modal-body attendpro-form-stack">
                    <x-modal-errors modal="schedule-modal" />
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="schedule-code">Code</label><input id="schedule-code" name="code" value="{{ $failedModal === 'schedule-modal' ? old('code') : '' }}" required></div><div class="kit-field"><label for="schedule-name">Name</label><input id="schedule-name" name="name" value="{{ $failedModal === 'schedule-modal' ? old('name') : '' }}" required></div></div>
                    <div class="attendpro-form-grid attendpro-schedule-basics-grid">
                        <input type="hidden" name="person_type" value="student">
                        <input type="hidden" name="location_type" value="classroom">
                        <div class="kit-field"><label>Coverage</label><input value="Students" readonly aria-readonly="true"></div>
                        <div class="kit-field"><label for="schedule-program">Program</label><select id="schedule-program" name="program"><option value="">All programs</option>@foreach ($programs as $program)<option value="{{ $program }}" @selected(($failedModal === 'schedule-modal' ? old('program') : '') === $program)>{{ $program }}</option>@endforeach</select></div>
                        <div class="kit-field" data-room-wrap><label for="schedule-room">Room</label><select id="schedule-room" name="room_code" data-schedule-room-select><option value="" @selected(($failedModal === 'schedule-modal' ? old('room_code') : '') === '')>Select a room</option>@foreach ($roomSuggestions as $suggestion)<option value="{{ $suggestion }}" @selected(($failedModal === 'schedule-modal' ? old('room_code') : '') === $suggestion)>{{ $suggestion }}</option>@endforeach</select><small class="kit-muted">Choose the room for this schedule.</small></div>
                    </div>
                    @php($selectedDays = $failedModal === 'schedule-modal' ? array_map('intval', (array) old('days_of_week', [])) : [1, 2, 3, 4, 5])
                    <fieldset class="attendpro-fieldset"><legend>Days of week</legend><div class="attendpro-check-row">@foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $index => $day)<label class="attendpro-check"><input type="checkbox" name="days_of_week[]" value="{{ $index + 1 }}" @checked(in_array($index + 1, $selectedDays, true))>{{ $day }}</label>@endforeach</div></fieldset>
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="schedule-start">Starts</label><input id="schedule-start" name="starts_at" type="time" value="{{ $failedModal === 'schedule-modal' ? old('starts_at') : '' }}" required></div><div class="kit-field"><label for="schedule-end">Ends</label><input id="schedule-end" name="ends_at" type="time" value="{{ $failedModal === 'schedule-modal' ? old('ends_at') : '' }}" required></div><div class="kit-field"><label for="schedule-opens">Check-in opens</label><input id="schedule-opens" name="check_in_opens_at" type="time" value="{{ $failedModal === 'schedule-modal' ? old('check_in_opens_at') : '' }}"></div><div class="kit-field"><label for="schedule-closes">Check-in closes</label><input id="schedule-closes" name="check_in_closes_at" type="time" value="{{ $failedModal === 'schedule-modal' ? old('check_in_closes_at') : '' }}"></div></div>
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="schedule-grace">Grace minutes</label><input id="schedule-grace" name="grace_minutes" type="number" min="0" max="180" value="{{ $failedModal === 'schedule-modal' ? old('grace_minutes', 10) : 10 }}" required></div><div class="kit-field"><label for="schedule-from">Effective from</label><input id="schedule-from" name="effective_from" type="date" value="{{ $failedModal === 'schedule-modal' ? old('effective_from') : '' }}"></div><div class="kit-field"><label for="schedule-until">Effective until</label><input id="schedule-until" name="effective_until" type="date" value="{{ $failedModal === 'schedule-modal' ? old('effective_until') : '' }}"></div></div>
                    <label class="attendpro-check"><input type="checkbox" name="checkout_required" value="1" @checked($failedModal === 'schedule-modal' ? old('checkout_required') : true)> Require a time-out scan</label>
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="kit-modal-foot"><button class="kit-button ghost" type="button" data-modal-close>Cancel</button><button class="kit-button primary" type="submit">Create schedule</button></div>
            </form>
        </div>

        <div class="kit-modal" id="department-modal" role="dialog" aria-modal="true" aria-labelledby="department-title" data-modal-auto-open="{{ $failedModal === 'department-modal' ? 'true' : 'false' }}">
            <form class="kit-modal-panel" method="POST" action="{{ route('departments.store') }}">
                @csrf
                <input type="hidden" name="_modal" value="department-modal">
                <div class="kit-modal-head"><div><strong id="department-title">Add or update a department</strong><span class="attendpro-modal-subtitle">Using an existing code updates that department.</span></div><button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button></div>
                <div class="kit-modal-body attendpro-form-stack">
                    <x-modal-errors modal="department-modal" />
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="department-code">Code</label><input id="department-code" name="code" value="{{ $failedModal === 'department-modal' ? old('code') : '' }}" required></div><div class="kit-field"><label for="department-name">Name</label><input id="department-name" name="name" value="{{ $failedModal === 'department-modal' ? old('name') : '' }}" required></div></div>
                </div>
                <div class="kit-modal-foot"><button class="kit-button ghost" type="button" data-modal-close>Cancel</button><button class="kit-button primary" type="submit">Save department</button></div>
            </form>
        </div>

        <div class="kit-modal" id="location-modal" role="dialog" aria-modal="true" aria-labelledby="location-title" data-modal-auto-open="{{ $failedModal === 'location-modal' ? 'true' : 'false' }}">
            <form class="kit-modal-panel" method="POST" action="{{ route('locations.store') }}">
                @csrf
                <input type="hidden" name="_modal" value="location-modal">
                <input type="hidden" name="timezone" value="{{ config('app.timezone') }}">
                <div class="kit-modal-head"><div><strong id="location-title">Add or update a location</strong><span class="attendpro-modal-subtitle">Terminals and schedules reference campus locations.</span></div><button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button></div>
                <div class="kit-modal-body attendpro-form-stack">
                    <x-modal-errors modal="location-modal" />
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="location-code">Code</label><input id="location-code" name="code" value="{{ $failedModal === 'location-modal' ? old('code') : '' }}" required></div><div class="kit-field"><label for="location-name">Name</label><input id="location-name" name="name" value="{{ $failedModal === 'location-modal' ? old('name') : '' }}" required></div></div>
                    <div class="kit-field"><label for="location-description">Description</label><textarea id="location-description" name="description">{{ $failedModal === 'location-modal' ? old('description') : '' }}</textarea></div>
                </div>
                <div class="kit-modal-foot"><button class="kit-button ghost" type="button" data-modal-close>Cancel</button><button class="kit-button primary" type="submit">Save location</button></div>
            </form>
        </div>
    @endpush
@endhasanyrole
