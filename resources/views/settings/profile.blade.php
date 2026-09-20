@extends('layouts.dashboard')

@section('title', 'My Profile | AttendPro')
@section('page-title', 'My Profile')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div>
                <h2>My profile</h2>
                <p>Manage the name, email address, and password for your AttendPro account.</p>
            </div>
            <span class="kit-dashboard-tag kit-dashboard-tag-blue"><i class="ph ph-user-circle" aria-hidden="true"></i>Account settings</span>
        </section>

        <form class="kit-dashboard-panel" method="POST" action="{{ route('settings.profile.update') }}">
            @csrf
            @method('PUT')
            <section class="attendpro-email-settings-intro">
                <span class="attendpro-python-server-icon"><i class="ph ph-identification-card" aria-hidden="true"></i></span>
                <div>
                    <span class="attendpro-section-kicker">ACCOUNT DETAILS</span>
                    <h3>Your sign-in information</h3>
                    <p>This information is used to identify your account throughout AttendPro.</p>
                </div>
            </section>
            <div class="attendpro-form-grid">
                <div class="kit-field"><label for="profile-name">Full name</label><input id="profile-name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></div>
                <div class="kit-field"><label for="profile-email">Email address</label><input id="profile-email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" required></div>
            </div>
            <div class="attendpro-email-settings-footer">
                <p><i class="ph ph-info" aria-hidden="true"></i> Your email address is also used for attendance confirmations when they are enabled.</p>
                <button class="kit-button primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i>Save profile</button>
            </div>
        </form>

        <form class="kit-dashboard-panel" method="POST" action="{{ route('settings.profile.password') }}">
            @csrf
            @method('PUT')
            <section class="attendpro-email-settings-intro">
                <span class="attendpro-python-server-icon"><i class="ph ph-lock-key" aria-hidden="true"></i></span>
                <div>
                    <span class="attendpro-section-kicker">SECURITY</span>
                    <h3>Change password</h3>
                    <p>Use your current password to secure this change.</p>
                </div>
            </section>
            <div class="attendpro-form-grid">
                <div class="kit-field"><label for="current-password">Current password</label><div class="attendpro-password-wrap"><input id="current-password" name="current_password" type="password" autocomplete="current-password" required><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div>@error('current_password', 'updatePassword')<small class="kit-field-error">{{ $message }}</small>@enderror</div>
                <div class="kit-field"><label for="new-password">New password</label><div class="attendpro-password-wrap"><input id="new-password" name="password" type="password" autocomplete="new-password" required><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div>@error('password', 'updatePassword')<small class="kit-field-error">{{ $message }}</small>@enderror</div>
                <div class="kit-field"><label for="new-password-confirmation">Confirm new password</label><div class="attendpro-password-wrap"><input id="new-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div></div>
            </div>
            <div class="attendpro-email-settings-footer">
                <p><i class="ph ph-shield-check" aria-hidden="true"></i> Use a new password that you do not reuse on another service.</p>
                <button class="kit-button primary" type="submit"><i class="ph ph-lock-key" aria-hidden="true"></i>Update password</button>
            </div>
        </form>
    </div>
@endsection
