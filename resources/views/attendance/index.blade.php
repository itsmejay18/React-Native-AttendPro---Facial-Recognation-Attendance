@extends('layouts.dashboard')

@section('title', 'Attendance Records | AttendPro')
@section('page-title', 'Attendance Records')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <x-attendance-tabs />

        <section class="kit-dashboard-section-head">
            <div><h2>Searchable attendance history</h2><p>Review time-in, time-out, status, source, schedule, and terminal information.</p></div>
            <div class="kit-action-row">
                <a class="kit-button secondary" href="{{ route('attendance.export', request()->query()) }}"><i class="ph ph-download-simple"></i>Export CSV</a>
                @hasanyrole('super_admin|attendance_admin')
                    <button class="kit-button primary" type="button" data-modal-open="#manual-attendance-modal"><i class="ph ph-plus"></i>Manual record</button>
                @endhasanyrole
            </div>
        </section>

        <div class="attendpro-filter-disclosure" data-filter-disclosure data-filter-open="{{ request()->hasAny(['date_from', 'date_to', 'type', 'department_id', 'location_id', 'status', 'search']) ? 'true' : 'false' }}">
            <div class="attendpro-filter-toolbar">
                <button class="kit-button secondary attendpro-filter-trigger" type="button" data-filter-trigger aria-expanded="false" aria-controls="attendance-record-filters"><i class="ph ph-funnel-simple"></i>Filters</button>
                @if (request()->hasAny(['date_from', 'date_to', 'type', 'department_id', 'location_id', 'status', 'search']))<span class="kit-badge info">Filters active</span>@endif
            </div>
        <form class="attendpro-filter-bar attendpro-filter-panel" id="attendance-record-filters" method="GET" data-filter-panel hidden>
            <div class="kit-field"><label for="date_from">From</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
            <div class="kit-field"><label for="date_to">To</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
            <div class="kit-field"><label for="type">Role</label><select id="type" name="type"><option value="">All</option><option value="student" @selected(request('type') === 'student')>Student</option></select></div>
            <div class="kit-field"><label for="department_id">Department</label><select id="department_id" name="department_id"><option value="">All departments</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="kit-field"><label for="location_id">Location</label><select id="location_id" name="location_id"><option value="">All locations</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="kit-field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>@foreach (\App\Models\AttendanceRecord::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="kit-field"><label for="search">Person</label><input id="search" name="search" value="{{ request('search') }}" placeholder="ID or name"></div>
            <div class="attendpro-filter-actions"><a class="kit-button ghost" href="{{ route('attendance.index') }}">Clear</a><button class="kit-button primary" type="submit"><i class="ph ph-magnifying-glass"></i>Search</button></div>
        </form>
        </div>

        <section class="kit-dashboard-panel">
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead><tr><th>Date</th><th>Person</th><th>Status</th><th>Time in</th><th>Time out</th><th>Location / terminal</th><th>Source</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->attendance_date->format('M d, Y') }}</td>
                            <td><strong>{{ $record->person->full_name }}</strong><br><span class="kit-muted">{{ $record->person->institution_id }} · {{ ucfirst($record->person->type) }}</span></td>
                            <td><span class="kit-badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ ucfirst($record->status) }}</span></td>
                            <td>{{ $record->time_in?->format('h:i:s A') ?? '—' }}</td>
                            <td>{{ $record->time_out?->format('h:i:s A') ?? '—' }}</td>
                            <td>{{ $record->schedule?->room_display ?? $record->location?->name ?? '—' }}<br><span class="kit-muted">{{ $record->terminal?->name ?? 'Manual entry' }}</span></td>
                            <td>{{ ucfirst($record->source) }}<br><span class="kit-muted">Rev. {{ $record->revision }}</span></td>
                            <td>
                                @hasanyrole('super_admin|attendance_admin')
                                    <button class="kit-button ghost attendpro-button-sm" type="button" data-modal-open="#correct-{{ $record->id }}">Correct</button>
                                @endhasanyrole
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="attendpro-empty">No attendance records match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $records->links() }}</div>
        </section>
    </div>
@endsection

@hasanyrole('super_admin|attendance_admin')
    @push('modals')
        @php($attendanceFailedModal = $errors->any() ? old('_modal') : null)
        <div class="kit-modal" id="manual-attendance-modal" role="dialog" aria-modal="true" aria-labelledby="manual-title" data-modal-auto-open="{{ $attendanceFailedModal === 'manual-attendance-modal' ? 'true' : 'false' }}">
            <form class="kit-modal-panel" method="POST" action="{{ route('attendance.store') }}">
                @csrf
                <input type="hidden" name="_modal" value="manual-attendance-modal">
                <div class="kit-modal-head"><div><strong id="manual-title">Add manual attendance</strong><span class="attendpro-modal-subtitle">Record an authorized attendance entry without leaving this page.</span></div><button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button></div>
                <div class="kit-modal-body attendpro-form-stack">
                    <x-modal-errors modal="manual-attendance-modal" />
                    <div class="kit-field"><label for="manual-person">Person</label><select id="manual-person" name="person_id" required><option value="">Select a person</option>@foreach ($people as $person)<option value="{{ $person->id }}" @selected($attendanceFailedModal === 'manual-attendance-modal' && (string) old('person_id') === (string) $person->id)>{{ $person->institution_id }} — {{ $person->full_name }}</option>@endforeach</select></div>
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="manual-date">Date</label><input id="manual-date" name="attendance_date" type="date" value="{{ $attendanceFailedModal === 'manual-attendance-modal' ? old('attendance_date', today()->toDateString()) : today()->toDateString() }}" required></div><div class="kit-field"><label for="manual-status">Status</label><select id="manual-status" name="status" required>@foreach (\App\Models\AttendanceRecord::STATUSES as $status)<option value="{{ $status }}" @selected($attendanceFailedModal === 'manual-attendance-modal' && old('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div></div>
                    <div class="attendpro-form-grid"><div class="kit-field"><label for="manual-time-in">Time in</label><input id="manual-time-in" name="time_in" type="datetime-local" value="{{ $attendanceFailedModal === 'manual-attendance-modal' ? old('time_in') : '' }}"></div><div class="kit-field"><label for="manual-time-out">Time out</label><input id="manual-time-out" name="time_out" type="datetime-local" value="{{ $attendanceFailedModal === 'manual-attendance-modal' ? old('time_out') : '' }}"></div></div>
                    <div class="kit-field"><label for="manual-location">Location</label><select id="manual-location" name="location_id"><option value="">None</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected($attendanceFailedModal === 'manual-attendance-modal' && (string) old('location_id') === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
                    <div class="kit-field"><label for="manual-notes">Reason / notes</label><textarea id="manual-notes" name="notes">{{ $attendanceFailedModal === 'manual-attendance-modal' ? old('notes') : '' }}</textarea></div>
                </div>
                <div class="kit-modal-foot"><button class="kit-button ghost" type="button" data-modal-close>Cancel</button><button class="kit-button primary" type="submit">Save record</button></div>
            </form>
        </div>

        @foreach ($records as $record)
            @php($correctionModal = 'correct-'.$record->id)
            @php($correctionFailed = $attendanceFailedModal === $correctionModal)
            <div class="kit-modal" id="{{ $correctionModal }}" role="dialog" aria-modal="true" aria-label="Correct attendance for {{ $record->person->full_name }}" data-modal-auto-open="{{ $correctionFailed ? 'true' : 'false' }}">
                <form class="kit-modal-panel" method="POST" action="{{ route('attendance.update', $record) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="_modal" value="{{ $correctionModal }}">
                    <div class="kit-modal-head"><strong>Correct {{ $record->person->full_name }}</strong><button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button></div>
                    <div class="kit-modal-body attendpro-form-stack">
                        <x-modal-errors :modal="$correctionModal" />
                        <div class="attendpro-form-grid"><div class="kit-field"><label>Time in</label><input name="time_in" type="datetime-local" value="{{ $correctionFailed ? old('time_in') : $record->time_in?->format('Y-m-d\TH:i') }}"></div><div class="kit-field"><label>Time out</label><input name="time_out" type="datetime-local" value="{{ $correctionFailed ? old('time_out') : $record->time_out?->format('Y-m-d\TH:i') }}"></div></div>
                        <div class="kit-field"><label>Status</label><select name="status">@foreach (\App\Models\AttendanceRecord::STATUSES as $status)<option value="{{ $status }}" @selected(($correctionFailed ? old('status') : $record->status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                        <div class="kit-field"><label>Correction reason *</label><textarea name="notes" required>{{ $correctionFailed ? old('notes') : $record->notes }}</textarea></div>
                    </div>
                    <div class="kit-modal-foot"><button class="kit-button ghost" type="button" data-modal-close>Cancel</button><button class="kit-button primary" type="submit">Save audited correction</button></div>
                </form>
            </div>
        @endforeach
    @endpush
@endhasanyrole
