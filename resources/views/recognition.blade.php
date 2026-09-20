@extends('layouts.dashboard')

@section('title', 'Recognition Center | Holy Child College of Davao')
@section('page-title', 'Recognition Center')

@section('content')
    <div class="kit-dashboard-content attendpro-recognition-content">
        <section class="kit-dashboard-section-head attendpro-recognition-heading">
            <div>
                <h2>Camera Recognition Scanner</h2>
                <p>Position one person in front of the camera, then scan to verify attendance.</p>
            </div>
            <span class="kit-dashboard-tag kit-dashboard-tag-yellow" data-python-service-state>
                <i class="ph ph-circle-notch" aria-hidden="true"></i>
                <span data-python-service-label>Checking recognition service</span>
            </span>
        </section>

        @unless ($enrollmentPerson)
        <section class="kit-dashboard-panel attendpro-recognition-session" aria-labelledby="attendance-session-title">
            <div>
                <span class="attendpro-section-kicker">SUPERVISED ATTENDANCE</span>
                <h3 id="attendance-session-title">Choose the class session before scanning</h3>
                <p>The selected session determines the program, scheduled time, and room Laravel will save with every attendance record.</p>
            </div>
            <div class="kit-field">
                <label for="attendance-session">Attendance session</label>
                <select id="attendance-session" data-attendance-session required>
                    <option value="">Select today’s class session</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}">
                            {{ \Illuminate\Support\Carbon::parse($session->starts_at)->format('h:i A') }}–{{ \Illuminate\Support\Carbon::parse($session->ends_at)->format('h:i A') }} · {{ $session->name }} ({{ $session->program ?? 'All programs' }}) · {{ $session->room_display }}
                        </option>
                    @endforeach
                </select>
                <small>{{ $sessions->count() ? 'Only active sessions scheduled for today are shown.' : 'No active class sessions are scheduled for today.' }}</small>
            </div>
        </section>
        @endunless

        <div
            class="attendpro-recognition-grid"
            data-recognition-scanner
            data-enrollment-mode="{{ $enrollmentPerson ? 'true' : 'false' }}"
            data-wasm-base="{{ asset('mediapipe/wasm') }}"
            data-model-url="{{ asset('models/blaze_face_short_range.tflite') }}"
            data-python-status-url="{{ route('recognition.python.status') }}"
            data-landmark-preview-url="{{ route('recognition.python.preview') }}"
            data-recognize-url="{{ route('recognition.python.scan') }}"
            data-enroll-url="{{ route('recognition.python.enroll') }}"
            data-csrf-token="{{ csrf_token() }}"
            data-frame-window="{{ config('attendpro.recognition.frame_window', 7) }}"
            data-enrollment-samples="{{ config('attendpro.recognition.enrollment_samples', 15) }}"
            data-enrollment-capture-frames="{{ config('attendpro.recognition.enrollment_samples', 15) }}"
        >
            <section class="attendpro-scanner-card" aria-labelledby="camera-feed-title">
                <div class="attendpro-scanner-card-head">
                    <div>
                        <span class="attendpro-section-kicker">LIVE CAMERA</span>
                        <h3 id="camera-feed-title">Center one face inside the guide</h3>
                    </div>
                    <span class="attendpro-camera-state" data-camera-state="idle">
                        <span aria-hidden="true"></span>
                        <strong data-camera-state-label>Camera off</strong>
                    </span>
                </div>

                <div class="attendpro-camera-viewport" data-camera-viewport>
                    <video class="attendpro-camera-video" data-camera-video autoplay muted playsinline aria-label="Live recognition camera feed"></video>
                    <canvas class="attendpro-camera-overlay" data-camera-overlay aria-hidden="true"></canvas>
                    <div class="attendpro-camera-frame" aria-hidden="true"><span></span></div>
                    <div class="attendpro-camera-placeholder" data-camera-placeholder>
                        <span class="attendpro-camera-placeholder-icon"><i class="ph ph-video-camera" aria-hidden="true"></i></span>
                        <strong>Ready when you are</strong>
                        <p>Select Start Camera and allow camera access when prompted.</p>
                    </div>
                    <div class="attendpro-camera-flash" data-camera-flash aria-hidden="true"></div>
                </div>

                <div class="attendpro-scanner-toolbar">
                    <div class="attendpro-scanner-status" role="status" aria-live="polite" data-scanner-status data-state="idle">
                        <span class="attendpro-scanner-status-icon"><i class="ph ph-info" aria-hidden="true"></i></span>
                        <div>
                            <strong data-scanner-status-title>Scanner is ready</strong>
                            <p data-scanner-status-detail>Start the camera, then face forward in even lighting.</p>
                        </div>
                    </div>

                    @if ($enrollmentPerson)
                        <label class="attendpro-check attendpro-scanner-enrollment-consent">
                            <input type="checkbox" data-enrollment-consent>
                            I confirm that documented consent for facial-data enrollment has been verified.
                        </label>
                        <p class="attendpro-enrollment-save-note"><i class="ph ph-info" aria-hidden="true"></i>First capture 15 replacement photos. The save button becomes available when all 15 are ready.</p>
                    @endif

                    <div class="attendpro-scanner-actions">
                        <button class="kit-button primary" type="button" data-camera-start>
                            <i class="ph ph-video-camera" aria-hidden="true"></i>
                            Start Camera
                        </button>
                        <button class="kit-button secondary" type="button" data-camera-capture disabled>
                            <i class="ph {{ $enrollmentPerson ? 'ph-images' : 'ph-scan' }}" aria-hidden="true"></i>
                            {{ $enrollmentPerson ? 'Capture 15 replacement photos' : 'Scan Face' }}
                        </button>
                        @if ($enrollmentPerson)
                            <button class="kit-button primary" type="button" data-enrollment-save disabled>
                                <i class="ph ph-floppy-disk" aria-hidden="true"></i>
                                Save face replacement
                            </button>
                        @endif
                        <button class="kit-button ghost" type="button" data-camera-stop disabled>
                            <i class="ph ph-video-camera-slash" aria-hidden="true"></i>
                            Stop
                        </button>
                    </div>
                </div>
            </section>

            @hasanyrole('super_admin|attendance_admin')
                <details class="attendpro-enrollment-workflow" id="face-enrollment" @if ($enrollmentPerson) open @endif>
                    <summary>
                        <span><i class="ph ph-user-focus" aria-hidden="true"></i></span>
                        <div>
                            <strong>{{ $enrollmentPerson ? 'Enroll '.$enrollmentPerson->full_name : 'Facial enrollment' }}</strong>
                            <small>{{ $enrollmentPerson ? 'Capture a clear face, verify consent, then collect the guided enrollment samples.' : 'Authorized staff can enroll an existing campus profile.' }}</small>
                        </div>
                        <i class="ph ph-caret-down" aria-hidden="true"></i>
                    </summary>
                    <div class="attendpro-enrollment-form">
                        <div class="kit-field">
                            <label for="enrollment-institution-id">Institution ID</label>
                            <input id="enrollment-institution-id" data-enrollment-id value="{{ $enrollmentPerson?->institution_id }}" placeholder="e.g. 2026-00001" autocomplete="off">
                        </div>
                        <div class="kit-field">
                            <label for="enrollment-retention">Retain until (optional)</label>
                            <input id="enrollment-retention" data-enrollment-retention type="date" min="{{ now()->addDay()->toDateString() }}">
                        </div>
                        <section class="attendpro-enrollment-sample-preview attendpro-recognition-sample-preview" data-enrollment-sample-preview hidden>
                            <div><strong>Captured samples</strong><span data-enrollment-sample-count>0 / {{ config('attendpro.recognition.enrollment_samples', 15) }}</span></div>
                            <div class="attendpro-enrollment-sample-grid" data-enrollment-sample-gallery aria-label="Enrollment sample previews"></div>
                            <p>The 15 consented enrollment images are saved privately with this facial profile. Attendance scans are not saved as raw photos.</p>
                        </section>
                    </div>
                </details>
            @endhasanyrole

            <div class="attendpro-recognition-guidance">
                <i class="ph ph-shield-check" aria-hidden="true"></i>
                <span><strong>Private and contactless.</strong> Attendance scans are processed securely and are not stored as raw photos.</span>
            </div>
        </div>

        <section class="attendpro-management-grid attendpro-recognition-history">
            <article class="kit-dashboard-panel">
                <div class="kit-dashboard-panel-head"><h3>Latest recognition activity</h3><span>Most recent 15 events</span></div>
                <div class="kit-list" data-recognition-activity data-activity-url="{{ route('recognition.activity') }}">
                    @forelse ($events as $event)
                        <div class="kit-list-item"><div class="kit-list-meta"><strong>{{ $event->person?->full_name ?? ucfirst($event->result).' face' }}</strong><span>{{ $event->terminal?->name ?? 'Laravel recognition' }} &middot; {{ $event->captured_at->diffForHumans() }} &middot; {{ $event->confidence !== null ? round($event->confidence * 100).'%' : 'No confidence' }}</span></div><span class="kit-badge {{ $event->result === 'matched' ? 'success' : 'warning' }}">{{ ucfirst($event->result) }}</span></div>
                    @empty
                        <p class="attendpro-empty">No recognition activity yet.</p>
                    @endforelse
                </div>
            </article>
            <article class="kit-dashboard-panel">
                <div class="kit-dashboard-panel-head"><h3>Exceptions requiring review</h3><span>{{ $pendingExceptions->total() }} pending</span></div>
                <div class="kit-list">
                    @forelse ($pendingExceptions as $event)
                        <div class="kit-list-item"><div class="kit-list-meta"><strong>{{ ucfirst($event->result) }} at {{ $event->terminal?->name ?? 'Laravel recognition' }}</strong><span>{{ $event->captured_at->format('M d, Y h:i:s A') }} &middot; {{ $event->failure_reason ?? 'No matching profile' }}</span></div>
                            @hasanyrole('super_admin|attendance_admin')
                                <form method="POST" action="{{ route('recognition.review', $event) }}">@csrf @method('PUT')<input type="hidden" name="review_status" value="reviewed"><button class="kit-button ghost attendpro-button-sm" type="submit">Mark reviewed</button></form>
                            @endhasanyrole
                        </div>
                    @empty
                        <p class="attendpro-empty">No recognition exceptions need review.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection

@push('modals')
    <div class="kit-modal attendpro-recognition-result-modal" id="recognition-result-modal" role="dialog" aria-modal="true" aria-labelledby="recognition-result-title">
        <div class="kit-modal-panel attendpro-recognition-result-panel" data-result-modal data-result-state="processing">
            <div class="kit-modal-head attendpro-result-modal-head">
                <div class="attendpro-result-modal-heading">
                    <span class="attendpro-result-modal-icon" data-result-modal-icon><i class="ph ph-circle-notch" aria-hidden="true"></i></span>
                    <div>
                        <span class="attendpro-section-kicker" data-result-modal-kicker>VERIFYING CAPTURE</span>
                        <strong id="recognition-result-title" data-result-modal-title>Matching identity&hellip;</strong>
                    </div>
                </div>
                <button class="kit-close" type="button" data-modal-close aria-label="Close recognition result"><i class="ph ph-x" aria-hidden="true"></i></button>
            </div>

            <div class="kit-modal-body attendpro-result-modal-body">
                <div class="attendpro-result-photo-column">
                    <div class="attendpro-capture-preview" data-capture-preview-wrap>
                        <img data-capture-preview alt="Captured face used for recognition">
                        <div data-capture-empty><i class="ph ph-user-focus" aria-hidden="true"></i><span>Preparing captured image</span></div>
                    </div>
                    <div class="attendpro-result-confidence-row">
                        <span>Detection <strong data-result-confidence>&mdash;</strong></span>
                        <span>Match <strong data-result-match-confidence>&mdash;</strong></span>
                        <span>Captured <strong data-result-time>&mdash;</strong></span>
                        <span hidden>Faces <strong data-result-face-count>&mdash;</strong></span>
                    </div>
                </div>

                <div class="attendpro-result-profile-column">
                    <div class="attendpro-result-identity">
                        <span class="attendpro-result-role" data-result-role>Checking profile</span>
                        <h2 data-result-name>Matching identity&hellip;</h2>
                        <p data-result-identity>Please wait while AttendPro verifies this face.</p>
                    </div>

                    <dl class="attendpro-result-profile-grid" data-result-profile>
                        <div><dt>Department</dt><dd data-result-department>&mdash;</dd></div>
                        <div data-profile-row="program"><dt>Program</dt><dd data-result-program>&mdash;</dd></div>
                        <div data-profile-row="year_level"><dt>Year Level</dt><dd data-result-year-level>&mdash;</dd></div>
                        <div data-profile-row="position"><dt>Position</dt><dd data-result-position>&mdash;</dd></div>
                        <div><dt>Email</dt><dd data-result-email>&mdash;</dd></div>
                        <div><dt>Phone</dt><dd data-result-phone>&mdash;</dd></div>
                        <div><dt>Profile Status</dt><dd data-result-profile-status>&mdash;</dd></div>
                        <div><dt>Joined On</dt><dd data-result-joined-on>&mdash;</dd></div>
                    </dl>

                    <div class="attendpro-result-attendance" data-result-attendance>
                        <span><i class="ph ph-calendar-check" aria-hidden="true"></i></span>
                        <div>
                            <small>ATTENDANCE RESULT</small>
                            <strong data-result-action>Processing&hellip;</strong>
                            <p data-result-attendance-detail>Attendance information will appear after verification.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kit-modal-foot attendpro-result-modal-foot">
                <p data-result-modal-message>The captured image is being checked securely.</p>
                <button class="kit-button secondary" type="button" data-result-retake><i class="ph ph-camera-rotate" aria-hidden="true"></i>Retake</button>
                <button class="kit-button primary" type="button" data-modal-close><i class="ph ph-scan" aria-hidden="true"></i>Scan another person</button>
            </div>
        </div>
    </div>
@endpush
