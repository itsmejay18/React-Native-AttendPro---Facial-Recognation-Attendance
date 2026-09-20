@extends('layouts.app')

@section('title', 'Confirm Password | AttendPro')

@section('body')
    <main class="attendpro-auth-page" id="main-content">
        <section class="attendpro-auth-card">
            <a href="{{ route('dashboard') }}" aria-label="Return to AttendPro dashboard">
                <img src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao">
            </a>
            <span class="kit-kicker">Security confirmation</span>
            <h1>Confirm your password</h1>
            <p class="kit-muted">This protected action requires your current password.</p>

            @if ($errors->any())
                <div class="attendpro-flash error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}" class="attendpro-form-stack">
                @csrf
                <div class="kit-field">
                    <label for="password">Current password</label>
                    <div class="attendpro-password-wrap">
                        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
                        <button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <button class="kit-button primary" type="submit"><i class="ph ph-lock-key-open"></i>Confirm password</button>
            </form>
        </section>
    </main>
@endsection
