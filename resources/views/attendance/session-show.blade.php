@extends('layouts.dashboard')

@section('title', 'Session Details | AttendPro')
@section('page-title', 'Attendance Session')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <x-attendance-tabs />

        <section class="kit-dashboard-section-head">
            <div>
                <h2>{{ $schedule?->name ?? 'Manual attendance' }} — {{ $date->format('M d, Y') }}</h2>
                <p>All attendance records captured for this session.</p>
            </div>
            <a class="kit-button secondary" href="{{ route('attendance.sessions', request()->only(['date_from', 'date_to', 'department_id', 'location_id', 'search'])) }}"><i class="ph ph-arrow-left"></i>Back to sessions</a>
        </section>

        <section class="kit-dashboard-panel">
            <dl class="attendpro-session-meta">
                <div><dt>Session</dt><dd>{{ $schedule ? 'SES-'.$schedule->code.'-'.$date->format('Ymd') : 'SES-MANUAL-'.$date->format('Ymd') }}</dd></div>
                <div><dt>Subject / Class</dt><dd>{{ $schedule?->name ?? 'Manual attendance' }}</dd></div>
                <div><dt>Department</dt><dd>{{ $schedule?->department?->name ?? '—' }}</dd></div>
                <div><dt>Room / Location</dt><dd>{{ $schedule?->room_display ?? $records->first()?->location?->name ?? '—' }}</dd></div>
                <div><dt>Schedule</dt><dd>@if ($schedule){{ \Carbon\Carbon::parse($schedule->starts_at)->format('h:i A') }} – {{ \Carbon\Carbon::parse($schedule->ends_at)->format('h:i A') }}@else —@endif</dd></div>
                <div><dt>Records</dt><dd>{{ number_format($records->total()) }}</dd></div>
            </dl>
        </section>

        <div class="attendpro-filter-disclosure" data-filter-disclosure data-filter-open="{{ request()->hasAny(['status', 'search']) ? 'true' : 'false' }}">
            <div class="attendpro-filter-toolbar">
                <button class="kit-button secondary attendpro-filter-trigger" type="button" data-filter-trigger aria-expanded="false" aria-controls="session-record-filters"><i class="ph ph-funnel-simple"></i>Filters</button>
                @if (request()->hasAny(['status', 'search']))<span class="kit-badge info">Filters active</span>@endif
            </div>
        <form class="attendpro-filter-bar attendpro-filter-panel" id="session-record-filters" method="GET" data-filter-panel hidden>
            <div class="kit-field"><label for="session-status">Status</label><select id="session-status" name="status"><option value="">All</option>@foreach (\App\Models\AttendanceRecord::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="kit-field"><label for="session-search">Person</label><input id="session-search" name="search" value="{{ request('search') }}" placeholder="ID or name"></div>
            <div class="attendpro-filter-actions"><a class="kit-button ghost" href="{{ route('attendance.sessions.show', ['schedule' => $schedule?->id ?? 'manual', 'date' => $date->toDateString()]) }}">Clear</a><button class="kit-button primary" type="submit"><i class="ph ph-magnifying-glass"></i>Search</button></div>
        </form>
        </div>

        <section class="kit-dashboard-panel">
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead><tr><th>Person</th><th>Status</th><th>Time in</th><th>Time out</th><th>Location</th><th>Source</th></tr></thead>
                    <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td><strong>{{ $record->person->full_name }}</strong><br><span class="kit-muted">{{ $record->person->institution_id }} · {{ ucfirst($record->person->type) }}</span></td>
                            <td><span class="kit-badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ ucfirst($record->status) }}</span></td>
                            <td>{{ $record->time_in?->format('h:i:s A') ?? '—' }}</td>
                            <td>{{ $record->time_out?->format('h:i:s A') ?? '—' }}</td>
                            <td>{{ $record->schedule?->room_display ?? $record->location?->name ?? '—' }}</td>
                            <td>{{ ucfirst($record->source) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="attendpro-empty">No attendance records found for the selected filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $records->links() }}</div>
        </section>
    </div>
@endsection
