@extends('layouts.app')

@section('title', 'Request Access | AttendPro')

@section('body')
    <main class="attendpro-auth-page" id="main-content">
        <section class="attendpro-auth-card">
            <a href="{{ route('home') }}" aria-label="Return to AttendPro home">
                <img src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao">
            </a>
            <span class="kit-kicker">Secure account request</span>
            <h1>Register for AttendPro</h1>
            <p class="kit-muted">New accounts begin as read-only reviewers and require administrator approval before sign-in.</p>

            @if ($errors->any())
                <div class="attendpro-flash error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="attendpro-form-stack">
                @csrf
                <div class="kit-field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
                </div>
                <div class="kit-field">
                    <label for="email">Institutional email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
                </div>
                <div class="kit-field">
                    <label for="password">Password</label>
                    <div class="attendpro-password-wrap">
                        <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                        <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                    </div>
                    <small>Use at least 12 characters with upper- and lowercase letters, a number, and a symbol.</small>
                </div>
                <div class="kit-field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="attendpro-password-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                        <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <button class="kit-button primary" type="submit"><i class="ph ph-user-plus"></i>Submit registration</button>
            </form>

            <p class="attendpro-auth-footer">Already registered? <a class="attendpro-auth-link" href="{{ route('login') }}">Sign in</a></p>
        </section>
    </main>
@endsection
