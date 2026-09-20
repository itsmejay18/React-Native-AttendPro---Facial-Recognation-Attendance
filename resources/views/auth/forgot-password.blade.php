@extends('layouts.app')

@section('title', 'Reset Password | AttendPro')

@section('body')
    <main class="attendpro-auth-page" id="main-content">
        <section class="attendpro-auth-card">
            <a href="{{ route('home') }}" aria-label="Return to AttendPro home">
                <img src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao">
            </a>
            <span class="kit-kicker">Account recovery</span>
            <h1>Reset your password</h1>
            <p class="kit-muted">Enter your institutional email and AttendPro will send a secure password reset link.</p>

            @if (session('status'))
                <div class="attendpro-flash success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="attendpro-flash error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="attendpro-form-stack">
                @csrf
                <div class="kit-field">
                    <label for="email">Institutional email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </div>
                <button class="kit-button primary" type="submit"><i class="ph ph-paper-plane-tilt"></i>Email reset link</button>
            </form>

            <p class="attendpro-auth-footer"><a class="attendpro-auth-link" href="{{ route('login') }}">Return to sign in</a></p>
        </section>
    </main>
@endsection
