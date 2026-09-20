@extends('layouts.public')

@section('title', 'AttendPro | Campus Facial Recognition Attendance')
@section('meta-description', 'A unified facial recognition attendance system for students, faculty, and staff of Holy Child College of Davao.')
@section('body-class', 'attendpro-theme-light')

@section('content')
    <section class="kit-hero attendpro-landing-hero" id="home">
        <div class="kit-container">
            <div class="kit-hero-grid">
                <div class="attendpro-hero-visual" aria-hidden="true">
                    <img class="attendpro-hero-image" src="{{ Vite::asset('resources/images/slideshow/background.png') }}" alt="">
                    <span class="attendpro-hero-visual-label"><i class="ph ph-shield-check"></i>Built for campus operations</span>
                </div>
                <div class="kit-hero-copy">
                    <span class="kit-kicker" style="color: var(--accent);">Campus-Wide Facial Recognition Attendance</span>
                    <h1>One secure attendance system for everyone on campus.</h1>
                    <p>
                        AttendPro gives Holy Child College of Davao one reliable way to record and monitor the attendance
                        of students, faculty, and staff through fast, contactless facial recognition.
                    </p>
                    <div class="kit-action-row" style="margin-top: 24px;">
                        <a class="kit-button primary" href="{{ route('attendance.kiosk') }}"><i class="ph ph-scan" aria-hidden="true"></i>Mark Attendance</a>
                        <a class="kit-button secondary" href="{{ route('dashboard') }}"><i class="ph ph-sign-in" aria-hidden="true"></i>{{ auth()->check() ? 'Open Dashboard' : 'Sign in' }}</a>
                    </div>
                    <div class="attendpro-public-attendance-status" data-public-attendance-status data-status-url="{{ route('attendance.kiosk.status') }}" data-state="checking" role="status" aria-live="polite">
                        <span class="attendpro-public-attendance-dot" aria-hidden="true"></span>
                        <div>
                            <strong data-public-attendance-label>Checking attendance station</strong>
                            <p data-public-attendance-detail>No login is required to mark attendance on this device.</p>
                        </div>
                    </div>
                </div>

                <div class="kit-hero-card">
                    <span class="kit-pill"><i class="ph ph-sparkle" aria-hidden="true"></i>One Connected Campus Attendance Flow</span>
                    <div class="kit-grid cols-2" style="margin-top: 18px;">
                        <div class="kit-card pad-md">
                            <span class="attendpro-hero-card-icon"><i class="ph ph-scan" aria-hidden="true"></i></span>
                            <strong style="display:block; font-size:1.15rem;">Fast Recognition</strong>
                            <p class="kit-muted">Verify registered people, then let Laravel apply the active class schedule.</p>
                        </div>
                        <div class="kit-card pad-md">
                            <span class="attendpro-hero-card-icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></span>
                            <strong style="display:block; font-size:1.15rem;">Multi-role Coverage</strong>
                            <p class="kit-muted">Manage students, faculty, and staff in one secure directory.</p>
                        </div>
                        <div class="kit-card pad-md">
                            <span class="attendpro-hero-card-icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></span>
                            <strong style="display:block; font-size:1.15rem;">Real-time Records</strong>
                            <p class="kit-muted">Log arrivals, departures, late entries, and absences as they happen.</p>
                        </div>
                        <div class="kit-card pad-md">
                            <span class="attendpro-hero-card-icon"><i class="ph ph-chart-line-up" aria-hidden="true"></i></span>
                            <strong style="display:block; font-size:1.15rem;">Clear Reports</strong>
                            <p class="kit-muted">Review attendance trends across departments and user groups.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="kit-section" id="how-it-works">
        <div class="kit-container">
            <div class="kit-section-header" style="margin-bottom: 18px;">
                <div>
                    <span class="kit-kicker" style="color: var(--accent);">How It Works</span>
                    <h2>A simple flow from face enrollment to reporting</h2>
                    <p>Every attendance record follows one consistent and auditable process.</p>
                </div>
            </div>

            <div class="kit-feature-grid">
                <article class="kit-feature-card">
                    <span class="attendpro-process-number">1</span>
                    <h3>Enroll each person</h3>
                    <p class="kit-muted">Create a student, faculty, or staff profile and securely capture the facial reference needed for recognition.</p>
                </article>
                <article class="kit-feature-card">
                    <span class="attendpro-process-number">2</span>
                    <h3>Configure attendance</h3>
                    <p class="kit-muted">Set schedules, attendance windows, locations, and rules for each department or user group.</p>
                </article>
                <article class="kit-feature-card">
                    <span class="attendpro-process-number">3</span>
                    <h3>Recognize and record</h3>
                    <p class="kit-muted">A successful face match lets Laravel record the person, schedule room, time, and attendance status automatically.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="kit-section" id="features">
        <div class="kit-container">
            <div class="kit-spotlight">
                <div class="kit-hero-grid" style="align-items: start;">
                    <div>
                        <span class="kit-kicker" style="color: var(--accent);">Built For Daily Campus Operations</span>
                        <h2 style="margin: 10px 0 12px;">Complete attendance visibility without manual logbooks.</h2>
                        <p class="kit-muted">Authorized personnel can monitor campus attendance while each department retains clear, filtered records for its students, faculty, and staff.</p>
                        <div class="kit-action-row" style="margin-top: 20px;">
                            <a class="kit-button primary" href="{{ route('dashboard') }}">Preview Dashboard</a>
                            <a class="kit-button secondary" href="{{ route('ui-kit.components') }}">View UI Components</a>
                        </div>
                    </div>
                    <div class="kit-panel">
                        <div class="kit-list">
                            <div class="kit-list-item">
                                <div class="kit-list-meta">
                                    <strong>Live recognition monitoring</strong>
                                    <span>See successful matches, late arrivals, and unrecognized scans from the Recognition Center.</span>
                                </div>
                            </div>
                            <div class="kit-list-item">
                                <div class="kit-list-meta">
                                    <strong>Institution-wide analytics</strong>
                                    <span>Compare attendance rates by role, department, schedule, location, week, or month.</span>
                                </div>
                            </div>
                            <div class="kit-list-item">
                                <div class="kit-list-meta">
                                    <strong>Export-ready records</strong>
                                    <span>Prepare filtered student, faculty, and staff records for reports and spreadsheet exports.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="kit-section" id="users">
        <div class="kit-container">
            <div class="kit-section-header" style="margin-bottom: 18px;">
                <div>
                    <span class="kit-kicker" style="color: var(--accent);">Complete Campus Coverage</span>
                    <h2>Attendance built around every institutional role</h2>
                </div>
            </div>
            <div class="kit-grid cols-4">
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-student" aria-hidden="true"></i>Students</span>
                    <h3 style="margin-top: 18px;">Reliable daily attendance</h3>
                    <p class="kit-muted">Record student arrival, departure, late, and absence statuses against assigned academic schedules.</p>
                </article>
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-chalkboard-teacher" aria-hidden="true"></i>Faculty</span>
                    <h3 style="margin-top: 18px;">Academic attendance records</h3>
                    <p class="kit-muted">Track faculty time records and allow authorized faculty to review attendance within their assigned areas.</p>
                </article>
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-identification-badge" aria-hidden="true"></i>Staff</span>
                    <h3 style="margin-top: 18px;">Workforce time monitoring</h3>
                    <p class="kit-muted">Capture staff attendance against office schedules, departments, and designated rooms.</p>
                </article>
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-shield-check" aria-hidden="true"></i>Administrators</span>
                    <h3 style="margin-top: 18px;">Complete system oversight</h3>
                    <p class="kit-muted">Manage identities, facial enrollment, schedules, rooms, reports, permissions, and audit activity.</p>
                </article>
            </div>
        </div>
    </section>
@endsection
