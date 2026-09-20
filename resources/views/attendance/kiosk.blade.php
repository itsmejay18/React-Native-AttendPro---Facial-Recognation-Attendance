@extends('layouts.public')

@section('title', 'Attendance Station | AttendPro')
@section('meta-description', 'Local facial-recognition attendance station for Holy Child College of Davao.')
@section('body-class', 'attendpro-theme-light')

@section('content')
    <section class="attendpro-kiosk-page">
        <div class="kit-container">
            <header class="attendpro-kiosk-heading">
                <div>
                    <span class="kit-kicker">No login required</span>
                    <h1>Mark your attendance</h1>
                    <p>Start the camera, center one face in the guide, then scan. Your attendance result will appear in a clear confirmation window.</p>
                </div>
                <span class="kit-dashboard-tag kit-dashboard-tag-yellow" data-python-service-state>
                    <i class="ph ph-circle-notch" aria-hidden="true"></i>
                    <span data-python-service-label>Checking attendance service</span>
                </span>
            </header>

            <div
                class="attendpro-recognition-grid attendpro-kiosk-scanner"
                data-recognition-scanner
                data-enrollment-mode="false"
                data-wasm-base="{{ asset('mediapipe/wasm') }}"
                data-model-url="{{ asset('models/blaze_face_short_range.tflite') }}"
                data-python-status-url="{{ route('attendance.kiosk.status') }}"
                data-landmark-preview-url="{{ route('attendance.kiosk.preview') }}"
                data-recognize-url="{{ route('attendance.kiosk.scan') }}"
                data-enroll-url=""
                data-csrf-token="{{ csrf_token() }}"
            >
                <section class="attendpro-scanner-card" aria-labelledby="kiosk-camera-title">
                    <div class="attendpro-scanner-card-head">
                        <div>
                            <span class="attendpro-section-kicker">LIVE ATTENDANCE CAMERA</span>
                            <h3 id="kiosk-camera-title">Center one face inside the guide</h3>
                        </div>
                        <span class="attendpro-camera-state" data-camera-state="idle">
                            <span aria-hidden="true"></span>
                            <strong data-camera-state-label>Camera off</strong>
                        </span>
                    </div>

                    <div class="attendpro-camera-viewport" data-camera-viewport>
                        <video class="attendpro-camera-video" data-camera-video autoplay muted playsinline aria-label="Live attendance camera feed"></video>
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
                        <div class="attendpro-scanner-actions">
                            <button class="kit-button primary" type="button" data-camera-start><i class="ph ph-video-camera" aria-hidden="true"></i>Start Camera</button>
                            <button class="kit-button secondary" type="button" data-camera-capture disabled><i class="ph ph-scan" aria-hidden="true"></i>Scan Face</button>
                            <button class="kit-button ghost" type="button" data-camera-stop disabled><i class="ph ph-video-camera-slash" aria-hidden="true"></i>Stop</button>
                        </div>
                    </div>
                </section>

                <div class="attendpro-recognition-guidance">
                    <i class="ph ph-shield-check" aria-hidden="true"></i>
                    <span><strong>Private and contactless.</strong> This page records attendance only. Starting and managing Python remains restricted to administrators.</span>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('modals')
    <div class="kit-modal attendpro-recognition-result-modal" id="recognition-result-modal" role="dialog" aria-modal="true" aria-labelledby="recognition-result-title">
        <div class="kit-modal-panel attendpro-recognition-result-panel" data-result-modal data-result-state="processing">
            <div class="kit-modal-head attendpro-result-modal-head">
                <div class="attendpro-result-modal-heading">
                    <span class="attendpro-result-modal-icon" data-result-modal-icon><i class="ph ph-circle-notch" aria-hidden="true"></i></span>
                    <div><span class="attendpro-section-kicker" data-result-modal-kicker>VERIFYING CAPTURE</span><strong id="recognition-result-title" data-result-modal-title>Matching identity&hellip;</strong></div>
                </div>
                <button class="kit-close" type="button" data-modal-close aria-label="Close attendance result"><i class="ph ph-x" aria-hidden="true"></i></button>
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
                        <div><small>ATTENDANCE RESULT</small><strong data-result-action>Processing&hellip;</strong><p data-result-attendance-detail>Attendance information will appear after verification.</p></div>
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
