<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta-description', 'Campus-wide facial recognition attendance for students, faculty, and staff of Holy Child College of Davao.')">
    <title>@yield('title', 'AttendPro')</title>
    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/faviconnew.png') }}">
    <link rel="apple-touch-icon" href="{{ Vite::asset('resources/images/faviconnew.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    <a class="attendpro-skip-link" href="#main-content">Skip to main content</a>
    @yield('body')
    @stack('modals')

    <div class="attendpro-loading-overlay" aria-hidden="true" aria-label="Holy Child College of Davao is loading">
        <div class="attendpro-loading-content" role="status" aria-live="polite">
            <div class="attendpro-loading-mark" aria-hidden="true">
                <img class="attendpro-loading-logo" src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="">
            </div>
            <div class="attendpro-loading-text">
                <span>Loading</span>
                <span class="attendpro-loading-dots" aria-hidden="true"><span></span><span></span><span></span></span>
            </div>
        </div>
    </div>
</body>
</html>
