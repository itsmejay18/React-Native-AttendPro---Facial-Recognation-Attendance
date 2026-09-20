@extends('layouts.dashboard')

@php
    $directoryTitle = 'Students Directory';
@endphp
@section('title', $directoryTitle.' | AttendPro')
@section('page-title', $directoryTitle)

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div><h2>{{ $directoryTitle }}</h2><p>Search, filter, and manage institutional and facial enrollment records.</p></div>
            <div class="kit-action-row attendpro-directory-actions">
                <nav class="attendpro-view-toggle" aria-label="Directory layout">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" class="{{ $view === 'list' ? 'is-active' : '' }}" @if ($view === 'list') aria-current="page" @endif><i class="ph ph-list" aria-hidden="true"></i>List</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}" class="{{ $view === 'grid' ? 'is-active' : '' }}" @if ($view === 'grid') aria-current="page" @endif><i class="ph ph-squares-four" aria-hidden="true"></i>Grid</a>
                </nav>
                @hasanyrole('super_admin|attendance_admin')
                    <button class="kit-button primary" type="button" data-modal-open="#register-person-modal"><i class="ph ph-user-plus"></i>Register person</button>
                @endhasanyrole
            </div>
        </section>

        <div class="attendpro-filter-disclosure" data-filter-disclosure data-filter-open="{{ request()->hasAny(['search', 'department_id', 'face']) ? 'true' : 'false' }}">
            <div class="attendpro-filter-toolbar">
                <button class="kit-button secondary attendpro-filter-trigger" type="button" data-filter-trigger aria-expanded="false" aria-controls="people-filters"><i class="ph ph-funnel-simple"></i>Filters</button>
                @if (request()->hasAny(['search', 'department_id', 'face']))<span class="kit-badge info">Filters active</span>@endif
            </div>
            <form class="attendpro-filter-bar attendpro-filter-panel" id="people-filters" method="GET" data-filter-panel hidden>
                <input type="hidden" name="view" value="{{ $view }}">
                <div class="kit-field"><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" placeholder="ID, name, or email"></div>
                <div class="kit-field"><label for="department_id">Department</label><select id="department_id" name="department_id"><option value="">All departments</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                <div class="kit-field"><label for="face">Face enrollment</label><select id="face" name="face"><option value="">Any</option><option value="enrolled" @selected(request('face') === 'enrolled')>Enrolled</option><option value="not_enrolled" @selected(request('face') === 'not_enrolled')>Not enrolled</option></select></div>
                <div class="attendpro-filter-actions"><a class="kit-button ghost" href="{{ route('people.index') }}">Clear</a><button class="kit-button primary" type="submit"><i class="ph ph-magnifying-glass"></i>Search</button></div>
            </form>
        </div>

        <section class="kit-dashboard-panel attendpro-person-panel">
            @if ($people->isNotEmpty())
                @if ($view === 'grid')
                    <div class="attendpro-person-grid" role="list" aria-label="{{ $directoryTitle }} records">
                        @foreach ($people as $person)
                            @php
                                $gridInitials = strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), array_slice(preg_split('/\s+/', trim($person->full_name)) ?: [], 0, 2))));
                                $gridShot = ($enrollmentShots[$person->id] ?? [])[0] ?? null;
                            @endphp
                            <button type="button" class="attendpro-person-card" role="listitem" data-modal-open="#person-modal-{{ $person->id }}" aria-haspopup="dialog">
                                @if ($gridShot)
                                    <img class="attendpro-person-card-photo" src="{{ $gridShot['url'] }}" alt="" loading="lazy">
                                @else
                                    <span class="attendpro-person-card-photo attendpro-person-card-empty" aria-hidden="true">{{ $gridInitials }}</span>
                                @endif
                                <span class="attendpro-person-card-body">
                                    <strong>{{ $person->full_name }}</strong>
                                    <span class="kit-muted">{{ $person->institution_id }} · {{ $person->department?->name ?? 'Unassigned' }}</span>
                                    <span class="attendpro-person-card-tags">
                                        <span class="kit-badge {{ $person->is_face_enrolled ? 'success' : 'warning' }}">{{ $person->is_face_enrolled ? 'Enrolled' : 'Pending' }}</span>
                                        <span class="kit-badge {{ $person->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($person->status) }}</span>
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @else
                <div class="attendpro-person-list" role="list" aria-label="{{ $directoryTitle }} records">
                    @foreach ($people as $person)
                        @php
                            $personInitials = strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), array_slice(preg_split('/\s+/', trim($person->full_name)) ?: [], 0, 2))));
                            $personShot = ($enrollmentShots[$person->id] ?? [])[0] ?? null;
                        @endphp
                        <button type="button" class="attendpro-person-row" role="listitem" data-modal-open="#person-modal-{{ $person->id }}" aria-haspopup="dialog">
                            @if ($personShot)
                                <img class="attendpro-person-avatar attendpro-person-photo" src="{{ $personShot['url'] }}" alt="" loading="lazy">
                            @else
                                <span class="attendpro-person-avatar" aria-hidden="true">{{ $personInitials }}</span>
                            @endif
                            <span class="attendpro-person-meta">
                                <strong>{{ $person->full_name }}</strong>
                                <span class="kit-muted">{{ $person->institution_id }} · {{ $person->email ?: 'No email' }}</span>
                            </span>
                            <span class="attendpro-person-dept">{{ $person->department?->name ?? 'Unassigned' }}</span>
                            <span class="attendpro-person-tags">
                                <span class="kit-badge {{ $person->is_face_enrolled ? 'success' : 'warning' }}">{{ $person->is_face_enrolled ? 'Enrolled' : 'Pending' }}</span>
                                <span class="kit-badge {{ $person->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($person->status) }}</span>
                            </span>
                            <i class="ph ph-caret-right attendpro-person-chevron" aria-hidden="true"></i>
                        </button>
                    @endforeach
                </div>
                @endif
            @else
                <p class="attendpro-empty">No student profiles match these filters.</p>
            @endif
            <div class="attendpro-pagination">{{ $people->links() }}</div>
        </section>
    </div>
@endsection

@push('modals')
    @foreach ($people as $person)
        @php
            $modalShots = $enrollmentShots[$person->id] ?? [];
        @endphp
        <div class="kit-modal attendpro-person-modal" id="person-modal-{{ $person->id }}" role="dialog" aria-modal="true" aria-labelledby="person-modal-title-{{ $person->id }}">
            <div class="kit-modal-panel attendpro-modal-xl">
                <div class="kit-modal-head">
                    <div class="attendpro-person-modal-head">
                        <div>
                            <span class="attendpro-person-eyebrow">{{ ucfirst($person->type) }} profile</span>
                            <strong id="person-modal-title-{{ $person->id }}">{{ $person->full_name }}</strong>
                            <span class="attendpro-modal-subtitle">{{ $person->institution_id }}</span>
                        </div>
                    </div>
                    <button class="kit-close" type="button" data-modal-close aria-label="Close profile details"><i class="ph ph-x" aria-hidden="true"></i></button>
                </div>
                <div class="kit-modal-body">
                    <div class="attendpro-person-body-grid">
                    <dl class="attendpro-portal-profile attendpro-person-detail">
                        <div><dt>Institution ID</dt><dd>{{ $person->institution_id }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $person->email ?: 'No email' }}</dd></div>
                        <div><dt>Department</dt><dd>{{ $person->department?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt>Role</dt><dd>{{ ucfirst($person->type) }}</dd></div>
                        <div><dt>Status</dt><dd><span class="kit-badge {{ $person->status === 'active' ? 'success' : 'danger' }}">{{ ucfirst($person->status) }}</span></dd></div>
                        <div><dt>Face enrollment</dt><dd><span class="kit-badge {{ $person->is_face_enrolled ? 'success' : 'warning' }}">{{ $person->is_face_enrolled ? 'Enrolled' : 'Pending' }}</span></dd></div>
                    </dl>
                    @php
                        $personShots = $modalShots;
                    @endphp
                    @if ($personShots !== [])
                        <section class="attendpro-modal-gallery" aria-label="Enrollment samples for {{ $person->full_name }}">
                            <div class="attendpro-modal-gallery-head"><strong>Enrollment samples</strong><span>{{ count($personShots) }} saved</span></div>
                            <div class="attendpro-enrollment-sample-grid attendpro-modal-sample-grid">
                                @foreach ($personShots as $shot)
                                    <img src="{{ $shot['url'] }}" alt="Enrollment sample {{ $shot['label'] }} for {{ $person->full_name }}" loading="lazy">
                                @endforeach
                            </div>
                            <p class="kit-muted">Consented references stored on the private disk, visible only to authorized roles.</p>
                        </section>
                    @endif
                    </div>
                </div>
                <div class="kit-modal-foot attendpro-person-modal-foot">
                    @hasanyrole('super_admin|attendance_admin')
                        <div class="attendpro-person-modal-actions">
                            <a class="kit-button secondary" href="{{ route('people.edit', $person) }}"><i class="ph ph-pencil-simple" aria-hidden="true"></i>Edit</a>
                            <a class="kit-button primary" href="{{ route('recognition', ['enroll' => $person->institution_id]) }}#face-enrollment"><i class="ph ph-user-focus" aria-hidden="true"></i>{{ $person->is_face_enrolled ? 'Replace face' : 'Enroll face' }}</a>
                        </div>
                        <form method="POST" action="{{ route('people.destroy', $person) }}" onsubmit="return confirm('Archive this profile?')">@csrf @method('DELETE')<button class="kit-button danger" type="submit"><i class="ph ph-archive" aria-hidden="true"></i>Archive</button></form>
                    @else
                        <span class="kit-muted">View only — contact an administrator to change this profile.</span>
                    @endhasanyrole
                </div>
            </div>
        </div>
    @endforeach
@endpush

@hasanyrole('super_admin|attendance_admin')
    @push('modals')
        @php
            $registerModalOpen = (old('_modal') === 'register-person-modal' && $errors->any())
                || session('open_modal') === 'register-person-modal';
            $newPerson = new \App\Models\Person([
                'type' => 'student',
                'status' => 'active',
            ]);
        @endphp
        <div class="kit-modal" id="register-person-modal" role="dialog" aria-modal="true" aria-labelledby="register-person-title" data-modal-auto-open="{{ $registerModalOpen ? 'true' : 'false' }}">
            <form
                class="kit-modal-panel attendpro-modal-xl attendpro-modal-scroll"
                method="POST"
                action="{{ route('people.store') }}"
                data-registration-enrollment
                data-python-status-url="{{ route('recognition.python.status') }}"
                data-landmark-preview-url="{{ route('recognition.python.preview') }}"
                data-csrf-token="{{ csrf_token() }}"
                data-enroll-url="{{ route('recognition.python.enroll') }}"
                data-wasm-base="{{ asset('mediapipe/wasm') }}"
                data-model-url="{{ asset('models/blaze_face_short_range.tflite') }}"
                data-directory-url="{{ route('people.index', ['type' => $selectedType ?? 'student']) }}"
                data-enrollment-samples="{{ config('attendpro.recognition.enrollment_samples', 15) }}"
                data-enrollment-capture-frames="{{ config('attendpro.recognition.enrollment_samples', 15) }}"
            >
                @csrf
                <input type="hidden" name="_modal" value="register-person-modal">
                <input type="hidden" name="enroll_face" value="1">
                <div class="kit-modal-head">
                    <div>
                        <strong id="register-person-title" data-registration-title>Register a person</strong>
                        <span class="attendpro-modal-subtitle" data-registration-subtitle>Step 1 of 2 · Enter the institutional profile information.</span>
                    </div>
                    <div class="attendpro-registration-progress" aria-label="Registration progress">
                        <span class="is-active" data-registration-progress="details"><i class="ph ph-identification-card" aria-hidden="true"></i><small>Profile</small></span>
                        <i aria-hidden="true"></i>
                        <span data-registration-progress="face"><i class="ph ph-user-focus" aria-hidden="true"></i><small>Face</small></span>
                    </div>
                    <button class="kit-close" type="button" data-modal-close data-registration-close aria-label="Close"><i class="ph ph-x"></i></button>
                </div>
                <div class="kit-modal-body attendpro-form-stack attendpro-registration-modal-body">
                    <div data-registration-details-step>
                        <div class="attendpro-form-stack">
                            <x-modal-errors modal="register-person-modal" />
                            <div class="attendpro-modal-errors" role="alert" data-registration-errors hidden>
                                <i class="ph ph-warning-circle" aria-hidden="true"></i>
                                <div><strong>Please review the information below.</strong><ul data-registration-error-list></ul></div>
                            </div>
                            @include('people._fields', ['person' => $newPerson, 'fieldPrefix' => 'new-person', 'accountRequired' => true])
                            <section class="attendpro-registration-account" aria-labelledby="registration-account-title">
                                <div class="attendpro-registration-account-copy">
                                    <span><i class="ph ph-key" aria-hidden="true"></i></span>
                                    <div>
                                        <strong id="registration-account-title">Login account</strong>
                                        <p>The email above and this password will be used to sign in. Access is activated after the face is enrolled.</p>
                                    </div>
                                </div>
                                <div class="attendpro-form-grid">
                                    <div class="kit-field">
                                        <label for="new-person-password">Temporary password *</label>
                                        <div class="attendpro-password-wrap">
                                            <input id="new-person-password" name="password" type="password" autocomplete="new-password" required>
                                            <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                    <div class="kit-field">
                                        <label for="new-person-password-confirmation">Confirm password *</label>
                                        <div class="attendpro-password-wrap">
                                            <input id="new-person-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                                            <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            <div class="attendpro-registration-requirement">
                                        <i class="ph ph-shield-check" aria-hidden="true"></i>
                                <div><strong>Facial enrollment is the next step.</strong><p>AttendPro automatically saves 15 face references for facial attendance.</p></div>
                            </div>
                        </div>
                    </div>

                    <div data-registration-face-step hidden>
                        <div class="attendpro-registration-person-summary">
                            <span><i class="ph ph-user-check" aria-hidden="true"></i></span>
                            <div><small>PROFILE SAVED</small><strong data-registration-person-name>New campus profile</strong><p><span data-registration-person-id>—</span> · <span data-registration-person-role>Person</span></p></div>
                            <span class="kit-badge warning" data-registration-service-badge><i class="ph ph-circle-notch" aria-hidden="true"></i><span data-registration-service-label>Checking facial service</span></span>
                        </div>

                        <div class="attendpro-registration-face-layout">
                            <section class="attendpro-registration-camera-card">
                                <div class="attendpro-registration-camera-head">
                                    <div><span class="attendpro-section-kicker">LIVE CAMERA</span><h3>Center one face inside the guide</h3></div>
                                    <span class="attendpro-camera-state" data-camera-state="idle" data-registration-camera-state><span aria-hidden="true"></span><strong data-registration-camera-state-label>Camera off</strong></span>
                                </div>
                                <div class="attendpro-camera-viewport" data-registration-camera-viewport>
                                    <video class="attendpro-camera-video" data-registration-camera-video autoplay muted playsinline aria-label="Live face enrollment camera"></video>
                                    <canvas class="attendpro-camera-overlay" data-registration-camera-overlay aria-hidden="true"></canvas>
                                    <div class="attendpro-camera-frame" aria-hidden="true"><span></span></div>
                                    <div class="attendpro-camera-placeholder" data-registration-camera-placeholder>
                                        <span class="attendpro-camera-placeholder-icon"><i class="ph ph-video-camera" aria-hidden="true"></i></span>
                                        <strong>Camera is ready</strong>
                                        <p>Select Start camera and allow browser access.</p>
                                    </div>
                                    <div class="attendpro-camera-flash" data-registration-camera-flash aria-hidden="true"></div>
                                </div>
                                <div class="attendpro-scanner-status" role="status" aria-live="polite" data-registration-status data-state="idle">
                                    <span class="attendpro-scanner-status-icon"><i class="ph ph-info" aria-hidden="true"></i></span>
                                    <div><strong data-registration-status-title>Ready for face enrollment</strong><p data-registration-status-detail>Start the camera, then look directly at it in even lighting.</p></div>
                                </div>
                                <div class="attendpro-scanner-actions attendpro-registration-camera-actions">
                                    <button class="kit-button secondary" type="button" data-registration-camera-start><i class="ph ph-video-camera" aria-hidden="true"></i>Start camera</button>
                                    <button class="kit-button primary" type="button" data-registration-enroll disabled><i class="ph ph-user-focus" aria-hidden="true"></i>Collect enrollment samples</button>
                                    <button class="kit-button secondary" type="button" data-registration-retake disabled><i class="ph ph-camera-rotate" aria-hidden="true"></i>Retake</button>
                                    <button class="kit-button ghost" type="button" data-registration-camera-stop disabled><i class="ph ph-video-camera-slash" aria-hidden="true"></i>Stop</button>
                                </div>
                            </section>

                            <aside class="attendpro-registration-enrollment-card">
                                <div><span class="attendpro-section-kicker">ENROLLMENT CHECK</span><h3>Confirm before capture</h3><p>AttendPro automatically saves 15 face references. There is no pose or quality-count requirement.</p></div>
                                <div class="attendpro-registration-capture" data-registration-capture-wrap>
                                    <img data-registration-capture alt="Captured face enrollment preview">
                                    <div data-registration-capture-empty><i class="ph ph-user-focus" aria-hidden="true"></i><span>No capture yet</span></div>
                                </div>
                                <section class="attendpro-enrollment-sample-preview" data-registration-sample-preview hidden>
                                    <div><strong>Captured face references</strong><span data-registration-sample-count>0 / 15</span></div>
                                    <div class="attendpro-enrollment-sample-grid" data-registration-sample-gallery aria-label="Temporary enrollment sample previews"></div>
                                    <p>Previewed only in this browser while enrollment is in progress; raw photos are not retained after submission.</p>
                                </section>
                                <div class="kit-field"><label for="registration-retention">Retain until (optional)</label><input id="registration-retention" data-registration-retention type="date" min="{{ now()->addDay()->toDateString() }}"></div>
                                <label class="attendpro-check attendpro-registration-consent"><input type="checkbox" data-registration-consent> Documented consent for facial-data enrollment has been verified.</label>
                                <div class="attendpro-registration-enrolled" data-registration-success hidden><i class="ph ph-check-circle" aria-hidden="true"></i><div><strong>Face enrolled successfully</strong><p>This person is now available to Recognition Center for attendance.</p></div></div>
                            </aside>
                        </div>
                    </div>
                </div>
                <div class="kit-modal-foot" data-registration-details-actions>
                    <button class="kit-button ghost" type="button" data-modal-close data-registration-close>Cancel</button>
                    <button class="kit-button primary" type="submit" data-registration-submit><i class="ph ph-arrow-right" aria-hidden="true"></i>Create account & scan face</button>
                </div>
                <div class="kit-modal-foot" data-registration-face-actions hidden>
                    <p class="kit-muted">Facial enrollment activates the person&rsquo;s login and attendance recognition.</p>
                    <a class="kit-button primary is-disabled" href="{{ route('people.index', ['type' => $selectedType ?? 'student']) }}" data-registration-finish aria-disabled="true" title="Complete facial enrollment before finishing"><i class="ph ph-check" aria-hidden="true"></i>Finish registration</a>
                </div>
            </form>
        </div>
    @endpush
@endhasanyrole
