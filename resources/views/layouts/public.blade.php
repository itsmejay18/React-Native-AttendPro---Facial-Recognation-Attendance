@extends('layouts.app')

@section('body')
    <div class="kit-shell">
        <header class="kit-public-header">
            <div class="kit-container">
                <div class="kit-public-nav">
                    <a class="kit-brand" href="{{ route('home') }}" aria-label="Holy Child attendance system home">
                        <img class="attendpro-public-logo" src="{{ Vite::asset('resources/images/faviconnew.png') }}" alt="Holy Child College of Davao">
                        <span class="kit-brand-copy attendpro-public-brand-copy" aria-hidden="true">
                            <small>Holy Child College of Davao</small>
                            <strong>AttendPro</strong>
                        </span>
                    </a>

                    <nav class="kit-public-links" aria-label="Main navigation">
                        <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}#home">Home</a>
                        <a class="{{ request()->routeIs('attendance.kiosk') ? 'is-active' : '' }}" href="{{ route('attendance.kiosk') }}">Attendance</a>
                        <a href="{{ route('home') }}#how-it-works">How It Works</a>
                        <a href="{{ route('home') }}#features">Features</a>
                        <a href="{{ route('home') }}#users">User Groups</a>
                    </nav>

                    <div class="kit-inline-actions">
                        @unless (request()->routeIs('home', 'attendance.kiosk'))
                            <button class="kit-icon-button" type="button" data-theme-toggle aria-label="Toggle color theme">
                                <i class="ph ph-moon" aria-hidden="true"></i>
                            </button>
                        @endunless
                        @auth
                            <a class="kit-button secondary" href="{{ route('dashboard') }}">Dashboard</a>
                        @else
                            <a class="kit-button secondary" href="{{ route('login') }}">Sign in</a>
                            <a class="kit-button primary" href="{{ route('register') }}">Register</a>
                        @endauth
                        <button class="kit-icon-button kit-public-menu" type="button" data-public-menu="#public-menu-panel" aria-label="Open menu" aria-controls="public-menu-panel" aria-expanded="false">
                            <i class="ph ph-list" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <nav class="kit-public-panel" id="public-menu-panel" aria-label="Mobile navigation">
                    <a href="{{ route('home') }}#home">Home</a>
                    <a href="{{ route('attendance.kiosk') }}">Attendance</a>
                    <a href="{{ route('home') }}#how-it-works">How It Works</a>
                    <a href="{{ route('home') }}#features">Features</a>
                    <a href="{{ route('home') }}#users">User Groups</a>
                    @auth
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}">Sign in</a>
                        <a href="{{ route('register') }}">Register</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main-content">
            @yield('content')
        </main>

        <footer class="attendpro-footer">
            <div class="kit-container attendpro-footer-main">
                <section class="attendpro-footer-about" aria-labelledby="footer-about-title">
                    <h2 id="footer-about-title">About</h2>
                    <img class="attendpro-footer-logo" src="{{ Vite::asset('resources/images/whitebackground.png') }}" alt="Holy Child College of Davao">
                    <p>
                        Holy Child College of Davao is a Bible-based Christian school in the Philippines,
                        founded by Mrs. Victoria D. Leuterio in 1981.
                    </p>
                </section>

                <section class="attendpro-footer-contact" aria-labelledby="footer-contact-title">
                    <h2 id="footer-contact-title">Get In Touch</h2>
                    <address>
                        <a href="mailto:info@holychild.edu.ph">
                            <i class="ph ph-envelope-simple" aria-hidden="true"></i>
                            <span>info@holychild.edu.ph</span>
                        </a>
                        <a href="tel:+639977269451">
                            <i class="ph ph-phone" aria-hidden="true"></i>
                            <span>+63 997 726 9451</span>
                        </a>
                    </address>

                    <nav class="attendpro-footer-socials" aria-label="Holy Child social media">
                        <a href="#" aria-label="Facebook"><i class="ph ph-facebook-logo" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Twitter"><i class="ph ph-twitter-logo" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Instagram"><i class="ph ph-instagram-logo" aria-hidden="true"></i></a>
                    </nav>
                </section>
            </div>

            <div class="attendpro-footer-bottom">
                <p>&copy; {{ date('Y') }} Holy Child College of Davao. All rights reserved.</p>
            </div>
        </footer>
    </div>
@endsection
