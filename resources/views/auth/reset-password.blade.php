@extends('layouts.app')

@section('title', 'Choose New Password | AttendPro')

@section('body')
    <main class="attendpro-auth-page" id="main-content">
        <section class="attendpro-auth-card">
            <a href="{{ route('home') }}" aria-label="Return to AttendPro home">
                <img src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao">
            </a>
            <span class="kit-kicker">Account recovery</span>
            <h1>Choose a new password</h1>
            <p class="kit-muted">Set a strong password for your AttendPro account.</p>

            @if ($errors->any())
                <div class="attendpro-flash error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.store') }}" class="attendpro-form-stack">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <div class="kit-field">
                    <label for="email">Institutional email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="username" required autofocus>
                </div>
                <div class="kit-field">
                    <label for="password">New password</label>
                    <div class="attendpro-password-wrap">
                        <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                        <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                    </div>
                    <small>Use at least 12 characters with upper- and lowercase letters, a number, and a symbol.</small>
                </div>
                <div class="kit-field">
                    <label for="password_confirmation">Confirm new password</label>
                    <div class="attendpro-password-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                        <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <button class="kit-button primary" type="submit"><i class="ph ph-key"></i>Reset password</button>
            </form>
        </section>
    </main>
@endsection
