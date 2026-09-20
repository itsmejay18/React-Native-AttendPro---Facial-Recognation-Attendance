@extends('layouts.dashboard')

@section('title', 'My Dashboard | AttendPro')
@section('page-title', 'My Dashboard')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div>
                <h2>Welcome, {{ $person?->first_name ?? auth()->user()->name }}</h2>
                <p>Your personal profile and attendance information. Only your own records are shown here.</p>
            </div>
            <a class="kit-button primary" href="{{ route('attendance.kiosk') }}"><i class="ph ph-scan" aria-hidden="true"></i>Mark attendance</a>
        </section>

        @if (! $person)
            <section class="kit-dashboard-panel attendpro-portal-warning">
                <i class="ph ph-warning-circle" aria-hidden="true"></i>
                <div><h3>Account link needs attention</h3><p>Your login is not linked to a campus profile. Ask an administrator to review this account.</p></div>
            </section>
        @else
            <section class="kit-dashboard-stats" aria-label="My attendance summary">
                <article class="kit-dashboard-stat-card">
                    <div class="kit-dashboard-stat-main"><div><p class="kit-dashboard-stat-label">Today</p><p class="kit-dashboard-stat-value attendpro-portal-status-value">{{ $todayRecord ? ucfirst($todayRecord->status) : 'Not recorded' }}</p><p class="kit-dashboard-stat-subtitle">{{ $todayRecord?->time_in?->format('h:i A') ?? 'Use the attendance station to check in' }}</p></div><span class="kit-dashboard-stat-icon"><i class="ph ph-calendar-check"></i></span></div>
                </article>
                <article class="kit-dashboard-stat-card">
                    <div class="kit-dashboard-stat-main"><div><p class="kit-dashboard-stat-label">This month</p><p class="kit-dashboard-stat-value">{{ $monthRecorded }}</p><p class="kit-dashboard-stat-subtitle">Recorded attendance days</p></div><span class="kit-dashboard-stat-icon"><i class="ph ph-calendar-dots"></i></span></div>
                </article>
                <article class="kit-dashboard-stat-card">
                    <div class="kit-dashboard-stat-main"><div><p class="kit-dashboard-stat-label">Face enrollment</p><p class="kit-dashboard-stat-value attendpro-portal-status-value">{{ $person->is_face_enrolled ? 'Ready' : 'Not enrolled' }}</p><p class="kit-dashboard-stat-subtitle">Recognition profile status</p></div><span class="kit-dashboard-stat-icon"><i class="ph ph-user-focus"></i></span></div>
                </article>
            </section>

            <section class="attendpro-portal-grid">
                <article class="kit-dashboard-panel">
                    <div class="kit-dashboard-panel-head"><h3>My campus profile</h3><span>{{ ucfirst($person->type) }}</span></div>
                    <dl class="attendpro-portal-profile">
                        <div><dt>Institution ID</dt><dd>{{ $person->institution_id }}</dd></div>
                        <div><dt>Full name</dt><dd>{{ $person->full_name }}</dd></div>
                        <div><dt>Department</dt><dd>{{ $person->department?->name ?? 'Unassigned' }}</dd></div>
                        @if ($person->type === 'student')
                            <div><dt>Program / year</dt><dd>{{ collect([$person->program, $person->year_level])->filter()->join(' · ') ?: 'Not provided' }}</dd></div>
                        @else
                            <div><dt>Position</dt><dd>{{ $person->position ?? 'Not provided' }}</dd></div>
                        @endif
                        <div><dt>Email</dt><dd>{{ $person->email }}</dd></div>
                        <div><dt>Status</dt><dd><span class="kit-badge {{ $person->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($person->status) }}</span></dd></div>
                    </dl>
                </article>

                <article class="kit-dashboard-panel">
                    <div class="kit-dashboard-panel-head"><h3>Recent attendance</h3><a href="{{ route('portal.attendance') }}">View all</a></div>
                    <div class="kit-list">
                        @forelse ($recentAttendance as $record)
                            <div class="kit-list-item"><div class="kit-list-meta"><strong>{{ $record->attendance_date->format('M d, Y') }}</strong><span>{{ $record->time_in?->format('h:i A') ?? 'No time in' }} · {{ $record->location?->name ?? 'Campus attendance' }}</span></div><span class="kit-badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ ucfirst($record->status) }}</span></div>
                        @empty
                            <p class="attendpro-empty">No attendance has been recorded yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        @endif
    </div>
@endsection
