@extends('layouts.public')

@section('title', 'UI Kit | AttendPro')

@section('content')
    <section class="kit-hero">
        <div class="kit-container">
            <div class="kit-hero-grid">
                <div class="kit-hero-copy">
                    <span class="kit-kicker" style="color: var(--accent);">AttendPro Design System</span>
                    <h1>One reusable interface for the entire attendance system.</h1>
                    <p>
                        The original static toolkit is now integrated with Laravel Blade and Vite. Its layouts, components,
                        responsive behavior, theme controls, icons, and visual tokens are available to every future module.
                    </p>
                    <div class="kit-action-row" style="margin-top: 24px;">
                        <a class="kit-button primary" href="{{ route('dashboard') }}"><i class="ph ph-layout" aria-hidden="true"></i>Dashboard UI</a>
                        <a class="kit-button secondary" href="{{ route('home') }}"><i class="ph ph-globe" aria-hidden="true"></i>Public UI</a>
                        <a class="kit-button ghost" href="{{ route('ui-kit.components') }}"><i class="ph ph-squares-four" aria-hidden="true"></i>Components</a>
                    </div>
                </div>

                <div class="kit-hero-card">
                    <div class="kit-grid cols-2">
                        <div class="kit-card pad-md">
                            <div class="kit-stat">
                                <div>
                                    <p class="kit-stat-label">Page shells</p>
                                    <p class="kit-stat-value">2</p>
                                    <p class="kit-stat-meta">Public and authenticated dashboard layouts.</p>
                                </div>
                                <span class="kit-stat-icon"><i class="ph ph-browser" aria-hidden="true"></i></span>
                            </div>
                        </div>
                        <div class="kit-card pad-md">
                            <div class="kit-stat">
                                <div>
                                    <p class="kit-stat-label">Asset flow</p>
                                    <p class="kit-stat-value">Vite</p>
                                    <p class="kit-stat-meta">Bundled CSS, JavaScript, fonts, and images.</p>
                                </div>
                                <span class="kit-stat-icon"><i class="ph ph-code" aria-hidden="true"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="kit-panel" style="margin-top: 18px;">
                        <h3>Ready for the next modules</h3>
                        <ul>
                            <li>Student, faculty, and staff registration with face enrollment</li>
                            <li>Attendance scheduling, terminals, and live recognition</li>
                            <li>Institution-wide records, analytics, exports, and user management</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="kit-section">
        <div class="kit-container">
            <div class="kit-panel-grid">
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-sidebar" aria-hidden="true"></i>Dashboard shell</span>
                    <h3 style="margin-top: 18px;">Sidebar, topbar, metrics, charts, and alerts</h3>
                    <p class="kit-muted">The core workspace for campus attendance administrators and authorized personnel.</p>
                </article>
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-browser" aria-hidden="true"></i>Public shell</span>
                    <h3 style="margin-top: 18px;">Responsive navigation, hero, features, and footer</h3>
                    <p class="kit-muted">The common foundation for public and authentication screens.</p>
                </article>
                <article class="kit-panel">
                    <span class="kit-pill"><i class="ph ph-paint-brush-broad" aria-hidden="true"></i>Components</span>
                    <h3 style="margin-top: 18px;">Buttons, badges, forms, tables, states, and modals</h3>
                    <p class="kit-muted">Reusable patterns for consistent future feature development.</p>
                </article>
            </div>
        </div>
    </section>
@endsection
