@extends('layouts.dashboard')

@section('title', 'Attendance Sessions | AttendPro')
@section('page-title', 'Attendance Sessions')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <x-attendance-tabs />

        <section class="kit-dashboard-section-head">
            <div><h2>Scheduled attendance sessions</h2><p>Each session groups attendance records by schedule and date. Open a session to inspect its records.</p></div>
        </section>

        <div class="attendpro-filter-disclosure" data-filter-disclosure data-filter-open="{{ request()->hasAny(['date_from', 'date_to', 'department_id', 'location_id', 'search']) ? 'true' : 'false' }}">
            <div class="attendpro-filter-toolbar">
                <button class="kit-button secondary attendpro-filter-trigger" type="button" data-filter-trigger aria-expanded="false" aria-controls="attendance-session-filters"><i class="ph ph-funnel-simple"></i>Filters</button>
                @if (request()->hasAny(['date_from', 'date_to', 'department_id', 'location_id', 'search']))<span class="kit-badge info">Filters active</span>@endif
            </div>
        <form class="attendpro-filter-bar attendpro-filter-panel" id="attendance-session-filters" method="GET" data-filter-panel hidden>
            <div class="kit-field"><label for="sessions-date-from">From</label><input id="sessions-date-from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
            <div class="kit-field"><label for="sessions-date-to">To</label><input id="sessions-date-to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
            <div class="kit-field"><label for="sessions-department">Department</label><select id="sessions-department" name="department_id"><option value="">All departments</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="kit-field"><label for="sessions-location">Location</label><select id="sessions-location" name="location_id"><option value="">All locations</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="kit-field"><label for="sessions-search">Search</label><input id="sessions-search" name="search" value="{{ request('search') }}" placeholder="Schedule or room"></div>
            <div class="attendpro-filter-actions"><a class="kit-button ghost" href="{{ route('attendance.sessions') }}">Clear</a><button class="kit-button primary" type="submit"><i class="ph ph-magnifying-glass"></i>Search</button></div>
        </form>
        </div>

        <section class="kit-dashboard-panel">
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead><tr><th>Session ID</th><th>Subject / Class</th><th>Coverage</th><th>Date</th><th>Time</th><th>Room</th><th>Students</th><th>Present</th><th>Late</th><th>Absent</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td><strong>{{ $session->session_id }}</strong></td>
                            <td><strong>{{ $session->subject }}</strong><br><span class="kit-muted">{{ $session->code }}</span></td>
                            <td>{{ $session->teacher }}</td>
                            <td>{{ $session->date->format('M d, Y') }}</td>
                            <td>{{ $session->starts_at ? \Carbon\Carbon::parse($session->starts_at)->format('h:i A') : '—' }}@if ($session->ends_at) – {{ \Carbon\Carbon::parse($session->ends_at)->format('h:i A') }}@endif</td>
                            <td>{{ $session->schedule?->room_display ?? $session->location?->name ?? '—' }}</td>
                            <td>{{ number_format($session->total) }}</td>
                            <td><span class="kit-badge success">{{ number_format($session->present) }}</span></td>
                            <td><span class="kit-badge warning">{{ number_format($session->late) }}</span></td>
                            <td><span class="kit-badge danger">{{ number_format($session->absent) }}</span></td>
                            <td><span class="kit-badge {{ $session->status === 'Active' ? 'success' : ($session->status === 'Cancelled' ? 'danger' : 'info') }}">{{ $session->status }}</span></td>
                            <td>
                                <a class="kit-button ghost attendpro-button-sm" href="{{ route('attendance.sessions.show', ['schedule' => $session->schedule?->id ?? 'manual', 'date' => $session->date->toDateString()]) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="attendpro-empty">No attendance sessions found for the selected filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $sessions->links() }}</div>
        </section>
    </div>
@endsection
