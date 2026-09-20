@extends('layouts.dashboard')

@section('title', 'API Settings | AttendPro')
@section('page-title', 'API Settings')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div>
                <h2>System API settings</h2>
                <p>Configure the secure system integrations used by Google sign-in and the local recognition service.</p>
            </div>
            <span class="kit-dashboard-tag kit-dashboard-tag-blue"><i class="ph ph-plugs-connected" aria-hidden="true"></i>Administrator only</span>
        </section>

        <form class="kit-dashboard-panel attendpro-api-settings" method="POST" action="{{ route('settings.api.update') }}">
            @csrf
            @method('PUT')

            <section class="attendpro-api-settings-head">
                <span class="attendpro-python-server-icon"><i class="ph ph-shield-check" aria-hidden="true"></i></span>
                <div>
                    <span class="attendpro-section-kicker">SECURE INTEGRATIONS</span>
                    <h3>Connection settings</h3>
                    <p>Secrets are write-only. Leave the Google client-secret field blank to retain the existing value.</p>
                </div>
            </section>

            <fieldset class="attendpro-api-fieldset">
                <legend>Google sign-in</legend>
                <div class="attendpro-api-fields">
                    <div class="kit-field"><label for="google-client-id">Google Client ID</label><input id="google-client-id" name="google_client_id" value="{{ old('google_client_id', $settings['google_client_id']) }}" autocomplete="off"></div>
                    <div class="kit-field"><label for="google-client-secret">Google Client Secret</label><input id="google-client-secret" name="google_client_secret" type="password" autocomplete="new-password" placeholder="{{ $settings['google_client_secret_configured'] ? 'Leave blank to keep the saved secret' : 'Enter a Google client secret' }}"><small>{{ $settings['google_client_secret_configured'] ? 'A secret is already configured and is never displayed.' : 'No secret is currently configured.' }}</small></div>
                    <div class="kit-field"><label for="google-redirect-uri">Google redirect URI</label><input id="google-redirect-uri" name="google_redirect_uri" type="url" value="{{ old('google_redirect_uri', $settings['google_redirect_uri']) }}" placeholder="http://127.0.0.1:8000/auth/google/callback"></div>
                </div>
            </fieldset>

            <fieldset class="attendpro-api-fieldset">
                <legend>Recognition service</legend>
                <div class="attendpro-api-fields">
                    <div class="kit-field"><label for="python-url">Python service URL</label><input id="python-url" name="python_url" type="url" value="{{ old('python_url', $settings['python_url']) }}" required></div>
                    <div class="kit-field"><label for="python-timeout">Python timeout (seconds)</label><input id="python-timeout" name="python_timeout" type="number" min="1" max="120" value="{{ old('python_timeout', $settings['python_timeout']) }}" required></div>
                    <div class="kit-field"><label for="laravel-api-url">Laravel recognition API URL</label><input id="laravel-api-url" name="laravel_api_url" type="url" value="{{ old('laravel_api_url', $settings['laravel_api_url']) }}" required></div>
                </div>
            </fieldset>

            <fieldset class="attendpro-api-fieldset">
                <legend>Access rules</legend>
                <div class="attendpro-api-fields">
                    <div class="kit-field"><label for="cors-origins">Allowed CORS origins</label><input id="cors-origins" name="cors_allowed_origins" value="{{ old('cors_allowed_origins', $settings['cors_allowed_origins']) }}" placeholder="http://127.0.0.1:8000, http://localhost:8000"><small>Comma-separated browser origins.</small></div>
                    <div class="kit-field"><label for="public-attendance-ips">Attendance station IPs</label><input id="public-attendance-ips" name="public_attendance_allowed_ips" value="{{ old('public_attendance_allowed_ips', $settings['public_attendance_allowed_ips']) }}" placeholder="127.0.0.1, ::1"><small>Comma-separated devices allowed to use the public station.</small></div>
                </div>
                <label class="attendpro-check attendpro-api-public-toggle"><input type="checkbox" name="public_attendance_enabled" value="1" @checked(old('public_attendance_enabled', $settings['public_attendance_enabled']))> Enable the public attendance station</label>
            </fieldset>

            <div class="attendpro-email-settings-footer">
                <p><i class="ph ph-info" aria-hidden="true"></i>The Python shared key is managed locally and is never displayed in the browser.</p>
                <button class="kit-button primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i>Save API settings</button>
            </div>
        </form>
    </div>
@endsection
