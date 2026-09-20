@extends('layouts.dashboard')

@section('title', 'Recognition Server Settings | AttendPro')
@section('page-title', 'Recognition Server')

@section('content')
    @php
        $recognitionBackends = \App\Services\RecognitionBackendConfiguration::BACKENDS;
        $selectedBackend = config('attendpro.recognition.face_backend', 'insightface');
    @endphp
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div>
                <h2>Local Recognition Server</h2>
                <p>Start and monitor the Python facial-recognition service used by Laravel on this device.</p>
            </div>
            <span class="kit-dashboard-tag kit-dashboard-tag-yellow" data-python-server-state>
                <i class="ph ph-circle-notch" aria-hidden="true"></i>
                <span data-python-server-state-label>Checking server</span>
            </span>
        </section>

        @if (config('attendpro.python_service.managed'))
            <section
                class="kit-dashboard-panel attendpro-python-server"
                data-python-server-panel
                data-python-status-url="{{ route('recognition.python.status') }}"
                data-python-start-url="{{ route('recognition.python.start') }}"
                data-python-backend-url="{{ route('recognition.python.backend') }}"
                data-python-server-url="{{ config('attendpro.python_service.url') }}"
                data-python-selected-backend="{{ $selectedBackend }}"
                data-csrf-token="{{ csrf_token() }}"
                data-state="checking"
            >
                <div class="attendpro-python-server-copy">
                    <span class="attendpro-python-server-icon"><i class="ph ph-cpu" aria-hidden="true"></i></span>
                    <div>
                        <span class="attendpro-section-kicker">LOCAL FACE RECOGNITION SERVER</span>
                        <h3>Python recognition service</h3>
                        <p data-python-server-feedback>Laravel is checking the local Python server.</p>
                    </div>
                </div>

                <dl class="attendpro-python-server-details">
                    <div><dt>Server</dt><dd>{{ config('attendpro.python_service.url') }}</dd></div>
                    <div><dt>Models</dt><dd data-python-server-models>Checking</dd></div>
                    <div><dt>Profiles</dt><dd data-python-server-profiles>&mdash;</dd></div>
                </dl>

                <div class="attendpro-python-server-actions">
                    <button class="kit-button primary" type="button" data-python-server-start>
                        <i class="ph ph-play" aria-hidden="true"></i>
                        Start recognition server
                    </button>
                    <button class="kit-button ghost" type="button" data-python-server-refresh>
                        <i class="ph ph-arrows-clockwise" aria-hidden="true"></i>
                        Refresh status
                    </button>
                </div>
            </section>

            <section class="kit-dashboard-panel attendpro-recognition-backend" aria-labelledby="recognition-backend-title">
                <div class="attendpro-recognition-backend-copy">
                    <span class="attendpro-python-server-icon"><i class="ph ph-brain" aria-hidden="true"></i></span>
                    <div>
                        <span class="attendpro-section-kicker">RECOGNITION MODEL</span>
                        <h3 id="recognition-backend-title">Choose the Python recognition backend</h3>
                        <p data-python-backend-description>{{ $recognitionBackends[$selectedBackend]['name'] ?? $recognitionBackends['insightface']['name'] }}.</p>
                    </div>
                </div>
                <div class="kit-field">
                    <label for="recognition-backend">Python model</label>
                    <select id="recognition-backend" data-python-backend-select>
                        @foreach ($recognitionBackends as $backend => $details)
                            <option value="{{ $backend }}" @selected($selectedBackend === $backend)>{{ $details['name'] }}</option>
                        @endforeach
                    </select>
                    <small data-python-backend-model>{{ $recognitionBackends[$selectedBackend]['model'] ?? $recognitionBackends['insightface']['model'] }}</small>
                </div>
                <div class="attendpro-recognition-backend-actions">
                    <button class="kit-button primary" type="button" data-python-backend-save><i class="ph ph-arrows-clockwise" aria-hidden="true"></i>Save and restart</button>
                    <p data-python-backend-feedback>Switching backends restarts the local Python server. Profiles are matched only by the model used when they were enrolled.</p>
                </div>
            </section>
        @else
            <section class="kit-dashboard-panel attendpro-api-note">
                <i class="ph ph-info" aria-hidden="true"></i>
                <div>
                    <strong>Laravel-managed server startup is disabled.</strong>
                    <p>Set <code>ATTENDPRO_PYTHON_MANAGED=true</code>, then clear Laravel&rsquo;s configuration cache.</p>
                </div>
            </section>
        @endif

        <section class="attendpro-management-grid">
            <article class="kit-dashboard-panel attendpro-info-card">
                <span class="attendpro-section-kicker">CONNECTION</span>
                <h3>Laravel to Python</h3>
                <p>Laravel connects to <strong>{{ config('attendpro.python_service.url') }}</strong>. Startup is restricted to localhost administrators and attendance staff.</p>
            </article>
            <article class="kit-dashboard-panel attendpro-info-card">
                <span class="attendpro-section-kicker">NEXT STEP</span>
                <h3>Use Recognition Center</h3>
                <p>Once the status shows Server running, open Recognition Center to enroll faces or record attendance.</p>
                <a class="kit-button secondary" href="{{ route('recognition') }}"><i class="ph ph-scan" aria-hidden="true"></i>Open Recognition Center</a>
            </article>
        </section>
    </div>
@endsection
