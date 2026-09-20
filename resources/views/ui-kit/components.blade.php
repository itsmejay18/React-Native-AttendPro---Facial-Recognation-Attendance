@extends('layouts.public')

@section('title', 'Components | AttendPro UI Kit')

@section('content')
    <section class="kit-hero" style="padding-bottom: 28px;">
        <div class="kit-container">
            <div class="kit-page-header" style="margin-bottom: 0;">
                <div>
                    <span class="kit-kicker" style="color: var(--accent);">Reusable Blocks</span>
                    <h1 style="margin-top: 8px;">AttendPro UI components</h1>
                    <p>Shared patterns for buttons, cards, forms, tables, states, and dialogs.</p>
                </div>
                <a class="kit-button secondary" href="{{ route('ui-kit.index') }}"><i class="ph ph-arrow-left" aria-hidden="true"></i>UI Kit Home</a>
            </div>
        </div>
    </section>

    <section class="kit-container kit-component-preview">
        <article class="kit-card pad-lg">
            <div class="kit-card-head">
                <div>
                    <h2>Buttons and badges</h2>
                    <p class="kit-card-subtitle">Primary actions and status labels used throughout the system.</p>
                </div>
            </div>
            <div class="kit-action-row">
                <button class="kit-button primary" type="button"><i class="ph ph-plus" aria-hidden="true"></i>Primary</button>
                <button class="kit-button secondary" type="button">Secondary</button>
                <button class="kit-button ghost" type="button">Ghost</button>
                <button class="kit-button danger" type="button">Danger</button>
            </div>
            <div class="kit-action-row" style="margin-top: 14px;">
                <span class="kit-badge success">Present</span>
                <span class="kit-badge warning">Late</span>
                <span class="kit-badge danger">Absent</span>
                <span class="kit-badge info">Excused</span>
            </div>
        </article>

        <div class="kit-grid cols-2">
            <article class="kit-card pad-lg">
                <div class="kit-card-head">
                    <div>
                        <h2>Statistics</h2>
                        <p class="kit-card-subtitle">Compact metrics for dashboards and reports.</p>
                    </div>
                </div>
                <div class="kit-grid cols-2">
                    <div class="kit-card pad-md">
                        <div class="kit-stat">
                            <div>
                                <p class="kit-stat-label">Present</p>
                                <p class="kit-stat-value">36</p>
                                <p class="kit-stat-meta">Across active campus terminals</p>
                            </div>
                            <span class="kit-stat-icon"><i class="ph ph-user-check" aria-hidden="true"></i></span>
                        </div>
                    </div>
                    <div class="kit-card pad-md">
                        <div class="kit-stat">
                            <div>
                                <p class="kit-stat-label">Absent</p>
                                <p class="kit-stat-value">4</p>
                                <p class="kit-stat-meta">Not yet recorded</p>
                            </div>
                            <span class="kit-stat-icon"><i class="ph ph-user-minus" aria-hidden="true"></i></span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="kit-card pad-lg">
                <div class="kit-card-head">
                    <div>
                        <h2>Empty state</h2>
                        <p class="kit-card-subtitle">For new modules and zero-result views.</p>
                    </div>
                </div>
                <div class="kit-empty">
                    <i class="ph ph-folder-simple-dashed" aria-hidden="true"></i>
                    <h3>No attendance windows yet</h3>
                    <p class="kit-muted">Create the first attendance window to begin recording students, faculty, and staff.</p>
                    <div class="kit-action-row" style="justify-content:center; margin-top: 16px;">
                        <button class="kit-button primary" type="button">Create attendance window</button>
                    </div>
                </div>
            </article>
        </div>

        <article class="kit-card pad-lg">
            <div class="kit-card-head">
                <div>
                    <h2>Form controls</h2>
                    <p class="kit-card-subtitle">A sample campus attendance window using the shared field styles.</p>
                </div>
            </div>
            <form class="kit-form-grid">
                <div class="kit-field">
                    <label for="sample-title">Attendance window</label>
                    <input id="sample-title" type="text" value="Main Campus Morning Attendance">
                </div>
                <div class="kit-field">
                    <label for="sample-status">Status</label>
                    <select id="sample-status">
                        <option>Scheduled</option>
                        <option>Active</option>
                        <option>Completed</option>
                    </select>
                </div>
                <div class="kit-field" style="flex-basis: 100%;">
                    <label for="sample-notes">Notes</label>
                    <textarea id="sample-notes">Facial recognition attendance for registered students, faculty, and staff entering the main campus.</textarea>
                </div>
            </form>
        </article>

        <article class="kit-card pad-lg">
            <div class="kit-card-head">
                <div>
                    <h2>Attendance table</h2>
                    <p class="kit-card-subtitle">Responsive multi-role attendance records with clear state badges.</p>
                </div>
            </div>
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead>
                        <tr><th>Person</th><th>Role</th><th>Time recorded</th><th>Status</th><th>Terminal</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Angela Reyes</td><td>Student</td><td>7:48 AM</td><td><span class="kit-badge success">Present</span></td><td>Main Entrance</td></tr>
                        <tr><td>Marco Santos</td><td>Faculty</td><td>8:07 AM</td><td><span class="kit-badge warning">Late</span></td><td>Faculty Entrance</td></tr>
                        <tr><td>Leah Garcia</td><td>Staff</td><td>&mdash;</td><td><span class="kit-badge danger">Not recorded</span></td><td>&mdash;</td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="kit-card pad-lg" style="margin-bottom: 34px;">
            <div class="kit-card-head">
                <div>
                    <h2>Modal dialog</h2>
                    <p class="kit-card-subtitle">Use this pattern for confirmations and short focused tasks.</p>
                </div>
                <button class="kit-button primary" type="button" data-modal-open="#component-modal">Open modal</button>
            </div>
        </article>
    </section>
@endsection

@push('modals')
    <div class="kit-modal" id="component-modal" role="dialog" aria-modal="true" aria-labelledby="component-modal-title">
        <div class="kit-modal-panel">
            <div class="kit-modal-head">
                <strong id="component-modal-title">Start campus attendance?</strong>
                <button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x" aria-hidden="true"></i></button>
            </div>
            <div class="kit-modal-body">
                <p class="kit-muted" style="margin: 0;">The Main Entrance terminal will begin accepting facial recognition attendance for students, faculty, and staff.</p>
            </div>
            <div class="kit-modal-foot">
                <button class="kit-button ghost" type="button" data-modal-close>Cancel</button>
                <button class="kit-button primary" type="button" data-modal-close>Start attendance</button>
            </div>
        </div>
    </div>
@endpush
