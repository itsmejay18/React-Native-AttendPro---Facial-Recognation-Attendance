@extends('layouts.dashboard')

@section('title', 'Email Notifications | AttendPro')
@section('page-title', 'Email Notifications')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div>
                <h2>Attendance email notifications</h2>
                <p>Send a confirmation from the configured mailbox after a recognized person records a time-in or time-out.</p>
            </div>
            <span class="kit-dashboard-tag kit-dashboard-tag-blue"><i class="ph ph-envelope-simple" aria-hidden="true"></i>SMTP sender</span>
        </section>

        <form class="kit-dashboard-panel attendpro-email-settings" method="POST" action="{{ route('settings.email-notifications.update') }}">
            @csrf
            @method('PUT')

            <section class="attendpro-email-settings-intro">
                <span class="attendpro-python-server-icon"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i></span>
                <div>
                    <span class="attendpro-section-kicker">ATTENDANCE CONFIRMATIONS</span>
                    <h3>Use your Gmail SMTP sender</h3>
                    <p>Attendance is saved before an email is sent. A delivery problem is logged and never cancels a completed attendance scan.</p>
                </div>
                <label class="attendpro-check"><input type="checkbox" name="enabled" value="1" @checked($settings['enabled'])> Send confirmation emails</label>
            </section>

            <div class="attendpro-form-grid attendpro-email-fields">
                <div class="kit-field"><label for="mail-mailer">Mailer</label><select id="mail-mailer" name="mailer"><option value="smtp" @selected($settings['mailer'] === 'smtp')>SMTP</option><option value="log" @selected($settings['mailer'] === 'log')>Log only (no emails sent)</option></select></div>
                <div class="kit-field"><label for="mail-host">SMTP host</label><input id="mail-host" name="host" value="{{ old('host', $settings['host']) }}" placeholder="smtp.gmail.com"></div>
                <div class="kit-field"><label for="mail-port">SMTP port</label><input id="mail-port" name="port" type="number" min="1" max="65535" value="{{ old('port', $settings['port']) }}" placeholder="587"></div>
                <div class="kit-field"><label for="mail-encryption">Encryption</label><select id="mail-encryption" name="scheme"><option value="smtp" @selected(old('scheme', $settings['scheme']) === 'smtp')>STARTTLS / TLS (recommended for Gmail port 587)</option><option value="smtps" @selected(old('scheme', $settings['scheme']) === 'smtps')>SSL/TLS (port 465)</option></select></div>
                <div class="kit-field"><label for="mail-timeout">Timeout (seconds)</label><input id="mail-timeout" name="timeout" type="number" min="1" max="60" value="{{ old('timeout', $settings['timeout']) }}"></div>
                <div class="kit-field"><label for="mail-username">SMTP username</label><input id="mail-username" name="username" value="{{ old('username', $settings['username']) }}" autocomplete="username" placeholder="your-email@gmail.com"></div>
                <div class="kit-field"><label for="mail-password">Gmail app password</label><input id="mail-password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep the saved password"><small>The existing password is never shown here.</small></div>
                <div class="kit-field"><label for="mail-from-address">From address</label><input id="mail-from-address" name="from_address" type="email" value="{{ old('from_address', $settings['from_address']) }}" placeholder="your-email@gmail.com"></div>
                <div class="kit-field"><label for="mail-from-name">From name</label><input id="mail-from-name" name="from_name" value="{{ old('from_name', $settings['from_name']) }}" placeholder="AttendPro"></div>
            </div>

            <div class="attendpro-email-settings-footer">
                <p><i class="ph ph-shield-check" aria-hidden="true"></i> For Gmail, use an App Password and TLS on port 587. Do not use your normal Gmail password.</p>
                <button class="kit-button primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i>Save email settings</button>
            </div>
        </form>

        <section class="kit-dashboard-panel attendpro-email-test" aria-labelledby="email-test-title">
            <div>
                <span class="attendpro-section-kicker">VERIFY DELIVERY</span>
                <h3 id="email-test-title">Send a test email</h3>
                <p>Save your Gmail SMTP settings first, then send a test to any email address. No attendance record will be created.</p>
            </div>
            <form method="POST" action="{{ route('settings.email-notifications.test') }}" class="attendpro-email-test-form">
                @csrf
                <div class="kit-field"><label for="mail-test-recipient">Test recipient</label><input id="mail-test-recipient" name="recipient" type="email" value="{{ old('recipient', auth()->user()->email) }}" required></div>
                <button class="kit-button secondary" type="submit"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>Send test email</button>
            </form>
        </section>
    </div>
@endsection
