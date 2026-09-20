@extends('layouts.app')

@section('body-class', 'kit-dashboard-page')

@section('body')
    <div class="kit-overlay" data-sidebar-close></div>

    <div class="kit-admin-shell">
        <aside class="kit-sidebar kit-dashboard-sidebar" aria-label="Dashboard navigation">
            <div class="kit-dashboard-sidebar-head">
                <a class="kit-dashboard-brand" href="{{ route('dashboard') }}">
                    <img class="attendpro-sidebar-logo" src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao crest">
                    <span class="kit-dashboard-brand-copy">
                        <p>Holy Child</p>
                        <strong>College of Davao</strong>
                    </span>
                </a>

                <button class="kit-sidebar-close" type="button" data-sidebar-close aria-label="Close sidebar">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>

            <x-dashboard-navigation />
        </aside>

        <main class="kit-main kit-dashboard-main" id="main-content">
            <div class="kit-frame kit-dashboard-frame">
                <header class="kit-dashboard-topbar">
                    <div class="kit-dashboard-topbar-left">
                        <button class="kit-icon-button kit-mobile-trigger" type="button" data-sidebar-open aria-label="Open sidebar">
                            <i class="ph ph-list" aria-hidden="true"></i>
                        </button>
                        <h1>@yield('page-title', 'Dashboard')</h1>
                    </div>

                    <div class="kit-dashboard-topbar-right">
                        <button class="kit-dashboard-circle-button" type="button" data-modal-open="#notification-modal" aria-label="Open notifications">
                            <i class="ph ph-bell" aria-hidden="true"></i>
                        </button>
                        <button class="kit-dashboard-circle-button" type="button" data-theme-toggle aria-label="Toggle color theme">
                            <i class="ph ph-sun" aria-hidden="true"></i>
                        </button>

                    </div>
                </header>

                <div class="kit-dashboard-scroll">
                    @if (session('success'))
                        <div class="attendpro-flash success" role="status">{{ session('success') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="attendpro-flash error" role="alert">{{ $errors->first() }}</div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </main>
    </div>
@endsection

@push('modals')
    <div class="kit-modal" id="notification-modal" role="dialog" aria-modal="true" aria-labelledby="notification-title">
        <div class="kit-modal-panel">
            <div class="kit-modal-head">
                <div>
                    <strong id="notification-title">Attendance notifications</strong>
                    <p class="kit-muted" style="margin: 6px 0 0;">Recent system alerts requiring attention.</p>
                </div>
                <button class="kit-close" type="button" data-modal-close aria-label="Close notifications">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="kit-modal-body">
                <div class="kit-list">
                    <div class="kit-list-item">
                        <div class="kit-list-meta">
                            <strong>Campus attendance is active</strong>
                            <span>Main Entrance is accepting facial recognition check-ins.</span>
                        </div>
                        <span class="kit-badge success">Live</span>
                    </div>
                    <div class="kit-list-item">
                        <div class="kit-list-meta">
                            <strong>Unrecognized scan needs review</strong>
                            <span>A face captured at the Administration Building did not match an active profile.</span>
                        </div>
                        <span class="kit-badge warning">Review</span>
                    </div>
                </div>
            </div>
            <div class="kit-modal-foot">
                <span class="kit-muted">Campus attendance notification center</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="kit-button primary" type="submit"><i class="ph ph-sign-out"></i>Sign out</button>
                </form>
            </div>
        </div>
    </div>
@endpush
