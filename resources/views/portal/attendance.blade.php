@extends('layouts.dashboard')

@section('title', 'My Attendance | AttendPro')
@section('page-title', 'My Attendance')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div><h2>My attendance history</h2><p>Your personal time-in, time-out, status, schedule, and location records.</p></div>
            <a class="kit-button primary" href="{{ route('attendance.kiosk') }}"><i class="ph ph-scan"></i>Mark attendance</a>
        </section>

        @if (! $person)
            <section class="kit-dashboard-panel attendpro-portal-warning"><i class="ph ph-warning-circle"></i><div><h3>No linked campus profile</h3><p>Ask an administrator to link this login before attendance records can be displayed.</p></div></section>
        @else
            <section class="kit-dashboard-panel">
                <div class="kit-table-wrap">
                    <table class="kit-table">
                        <thead><tr><th>Date</th><th>Status</th><th>Time in</th><th>Time out</th><th>Schedule</th><th>Location</th></tr></thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr>
                                    <td><strong>{{ $record->attendance_date->format('M d, Y') }}</strong></td>
                                    <td><span class="kit-badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ ucfirst($record->status) }}</span></td>
                                    <td>{{ $record->time_in?->format('h:i:s A') ?? '—' }}</td>
                                    <td>{{ $record->time_out?->format('h:i:s A') ?? '—' }}</td>
                                    <td>{{ $record->schedule?->name ?? 'General attendance' }}</td>
                                    <td>{{ $record->schedule?->room_display ?? $record->location?->name ?? $record->terminal?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="attendpro-empty">No attendance records are available yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="attendpro-pagination">{{ $records->links() }}</div>
            </section>
        @endif
    </div>
@endsection
