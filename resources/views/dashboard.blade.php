@extends('layouts.dashboard')

@section('title', 'Dashboard | AttendPro')
@section('page-title', 'Dashboard')

@section('content')
    <div class="kit-dashboard-content">
        <header class="attendpro-dashboard-intro">
            <div class="attendpro-dashboard-intro-copy">
                <p class="attendpro-dashboard-eyebrow">Holy Child College of Davao</p>
                <h2>Student attendance dashboard</h2>
                <p>Monitor student recognition activity and attendance from one workspace.</p>
                <span>{{ now()->format('l, F j, Y') }} &middot; Davao City</span>
            </div>
        </header>

        <section class="kit-dashboard-section-head attendpro-dashboard-overview-head">
            <div>
                <h2>Attendance Overview</h2>
                <p>Today&rsquo;s campus-wide recognition activity and attendance insights</p>
            </div>
            <span class="kit-dashboard-tag kit-dashboard-tag-blue">Live overview</span>
        </section>

        <section class="kit-dashboard-stats" aria-label="Attendance statistics">
            <article class="kit-dashboard-stat-card">
                <div class="kit-dashboard-stat-main">
                    <div>
                        <p class="kit-dashboard-stat-label">Students</p>
                        <p class="kit-dashboard-stat-value">{{ number_format($peopleCounts['student'] ?? 0) }}</p>
                        <p class="kit-dashboard-stat-subtitle">Registered student profiles</p>
                    </div>
                    <div class="attendpro-dashboard-stat-actions">
                        <span class="kit-dashboard-stat-icon"><i class="ph ph-student" aria-hidden="true"></i></span>
                        <span class="kit-dashboard-tag kit-dashboard-tag-blue">Student records</span>
                    </div>
                </div>
            </article>

            <article class="kit-dashboard-stat-card">
                <div class="kit-dashboard-stat-main">
                    <div>
                        <p class="kit-dashboard-stat-label">Present Today</p>
                        <p class="kit-dashboard-stat-value">{{ number_format(($attendanceCounts['present'] ?? 0) + ($attendanceCounts['late'] ?? 0)) }}</p>
                        <p class="kit-dashboard-stat-subtitle">Across registered students</p>
                    </div>
                    <div class="attendpro-dashboard-stat-actions">
                        <span class="kit-dashboard-stat-icon"><i class="ph ph-user-check" aria-hidden="true"></i></span>
                        <span class="kit-dashboard-tag kit-dashboard-tag-green">{{ $attendanceRate }}% recorded</span>
                    </div>
                </div>
            </article>
        </section>

        <section class="kit-dashboard-chart-grid">
            <article class="kit-dashboard-panel kit-dashboard-panel-wide">
                <div class="kit-dashboard-panel-head">
                    <h3>Institution-wide Attendance Rate</h3>
                    <span>Registered students</span>
                </div>
                <div class="kit-dashboard-line-chart-wrap">
                    <canvas id="kitTrendChart" width="940" height="400" aria-label="Institution-wide monthly attendance rate chart" data-labels='@json($trendLabels)' data-values="@json($trendValues)" data-max-value="100" data-step-count="4"></canvas>
                </div>
            </article>

            <article class="kit-dashboard-panel">
                <div class="kit-dashboard-panel-head">
                    <h3>Today&rsquo;s Attendance Mix</h3>
                    <span>Registered students</span>
                </div>
                @php
                    $mixTotal = ($attendanceCounts['present'] ?? 0) + ($attendanceCounts['late'] ?? 0) + ($attendanceCounts['absent'] ?? 0);
                    $presentPercent = $mixTotal ? round((($attendanceCounts['present'] ?? 0) / $mixTotal) * 100) : 0;
                    $latePercent = $mixTotal ? round((($attendanceCounts['late'] ?? 0) / $mixTotal) * 100) : 0;
                    $absentPercent = $mixTotal ? max(0, 100 - $presentPercent - $latePercent) : 0;
                    $mixBackground = $mixTotal
                        ? "conic-gradient(#22c55e 0 {$presentPercent}%, #fbbf24 {$presentPercent}% ".($presentPercent + $latePercent)."%, #f87171 ".($presentPercent + $latePercent).'% 100%)'
                        : 'var(--bg-page)';
                @endphp
                <div class="kit-dashboard-pie attendpro-dashboard-pie" style="background: {{ $mixBackground }}" role="img" aria-label="{{ $presentPercent }} percent present, {{ $latePercent }} percent late, and {{ $absentPercent }} percent absent"></div>
                <div class="attendpro-legend" aria-hidden="true">
                    <span><i style="background:#22c55e"></i>Present {{ $presentPercent }}%</span>
                    <span><i style="background:#fbbf24"></i>Late {{ $latePercent }}%</span>
                    <span><i style="background:#f87171"></i>Absent {{ $absentPercent }}%</span>
                </div>
            </article>
        </section>

        <section class="attendpro-summary-strip">
            <span><i class="ph ph-warning-circle"></i><strong>{{ $pendingExceptions }}</strong> recognition exceptions awaiting review</span>
        </section>
    </div>
@endsection
