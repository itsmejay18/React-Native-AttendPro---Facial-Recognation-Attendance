@extends('layouts.app')

@section('title', 'Administrator Sign In | AttendPro')

@section('body')
    <main class="attendpro-auth-page" id="main-content">
        <section class="attendpro-auth-card">
            <a href="{{ route('home') }}" aria-label="Return to AttendPro home">
                <img src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao">
            </a>
            <span class="kit-kicker">Authorized access only</span>
            <h1>Sign in to AttendPro</h1>
            <p class="kit-muted">Attendance records and facial enrollment data are restricted to authorized personnel.</p>

            @if (session('status'))
                <div class="attendpro-flash success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="attendpro-flash error" role="alert">{{ $errors->first() }}</div>
            @endif

                <form method="POST" action="{{ route('login.store') }}" class="attendpro-form-stack">
                @csrf
                <div class="kit-field">
                    <label for="email">Institutional email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </div>
                <div class="kit-field">
                    <label for="password">Password</label>
                    <div class="attendpro-password-wrap">
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="attendpro-auth-options">
                    <label class="attendpro-check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                    <a class="attendpro-auth-link" href="{{ route('password.request') }}">Forgot password?</a>
                </div>
                <button class="kit-button primary" type="submit"><i class="ph ph-sign-in"></i>Sign in</button>
                </form>
                <div class="attendpro-auth-divider"><span>or</span></div>
                @if (filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect')))
                    <a class="kit-button secondary attendpro-google-login" href="{{ route('auth.google.redirect') }}"><i class="ph ph-google-logo" aria-hidden="true"></i>Continue with Google</a>
                @else
                    <p class="kit-muted attendpro-google-login-note">Google sign-in is unavailable until an administrator configures the Google OAuth credentials.</p>
                @endif

            <p class="attendpro-auth-footer">Need an account? <a class="attendpro-auth-link" href="{{ route('register') }}">Request access</a></p>
        </section>
    </main>
@endsection
