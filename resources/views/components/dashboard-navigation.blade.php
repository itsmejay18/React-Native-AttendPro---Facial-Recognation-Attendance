@php
    $user = auth()->user();
    $campusUser = $user->hasAnyRole(\App\Models\Person::TYPES);
    $primaryItems = [[
        'label' => 'Dashboard',
        'icon' => 'ph-gauge',
        'url' => route('dashboard'),
        'active' => request()->routeIs('dashboard'),
    ]];

    if ($campusUser) {
        $sections = [[
            'label' => 'My Account',
            'icon' => 'ph-user-circle',
            'items' => [
                ['label' => 'My Attendance', 'icon' => 'ph-calendar-check', 'url' => route('portal.attendance'), 'active' => request()->routeIs('portal.attendance')],
                ['label' => 'Attendance Station', 'icon' => 'ph-scan', 'url' => route('attendance.kiosk'), 'active' => false],
            ],
        ]];

        $sections[] = [
            'label' => 'Settings',
            'icon' => 'ph-gear-six',
            'items' => [
                ['label' => 'My Profile', 'icon' => 'ph-user-circle', 'url' => route('settings.profile'), 'active' => request()->routeIs('settings.profile*')],
            ],
        ];
    } else {
        $sections = [
            [
                'label' => 'Attendance Operations',
                'icon' => 'ph-scan',
                'items' => [
                    ['label' => 'Recognition Center', 'icon' => 'ph-scan', 'url' => route('recognition'), 'active' => request()->routeIs('recognition*') && request('panel') !== 'server'],
                    ['label' => 'Attendance Sessions', 'icon' => 'ph-calendar-check', 'url' => route('attendance.sessions'), 'active' => request()->routeIs('attendance.sessions*')],
                    ['label' => 'Attendance Records', 'icon' => 'ph-folder-open', 'url' => route('attendance.index'), 'active' => request()->routeIs('attendance.index')],
                    ['label' => 'Schedules', 'icon' => 'ph-clock', 'url' => route('schedules.index'), 'active' => request()->routeIs('schedules.*')],
                ],
            ],
            [
                'label' => 'People Directory',
                'icon' => 'ph-users-three',
                'items' => [
                    ['label' => 'Students', 'icon' => 'ph-student', 'url' => route('people.index', ['type' => 'student']), 'active' => request()->routeIs('people.*') && request('type') === 'student'],
                ],
            ],
            [
                'label' => 'Insights & Oversight',
                'icon' => 'ph-chart-line-up',
                'items' => [
                    ['label' => 'Reports & Analytics', 'icon' => 'ph-chart-line-up', 'url' => route('reports.index'), 'active' => request()->routeIs('reports.*')],
                    ['label' => 'Activity Logs', 'icon' => 'ph-clipboard-text', 'url' => route('audit-logs.index'), 'active' => request()->routeIs('audit-logs.*')],
                ],
            ],
        ];

        if ($user->hasAnyRole(['super_admin', 'attendance_admin'])) {
            $settingsItems = [
                ['label' => 'My Profile', 'icon' => 'ph-user-circle', 'url' => route('settings.profile'), 'active' => request()->routeIs('settings.profile*')],
            ];

            if (config('attendpro.python_service.managed')) {
                $settingsItems[] = ['label' => 'Recognition Server', 'icon' => 'ph-cpu', 'url' => route('settings.recognition-server'), 'active' => request()->routeIs('settings.recognition-server')];
            }

            $settingsItems[] = ['label' => 'API Settings', 'icon' => 'ph-plugs-connected', 'url' => route('settings.api'), 'active' => request()->routeIs('settings.api*')];
            $settingsItems[] = ['label' => 'Email Notifications', 'icon' => 'ph-envelope-simple', 'url' => route('settings.email-notifications'), 'active' => request()->routeIs('settings.email-notifications*')];
            $settingsItems[] = ['label' => 'Attendance Staff', 'icon' => 'ph-identification-badge', 'url' => route('attendance.staff'), 'active' => request()->routeIs('users.*', 'attendance.staff')];
            $sections[] = ['label' => 'Settings', 'icon' => 'ph-gear-six', 'items' => $settingsItems];
        } else {
            $sections[] = [
                'label' => 'Settings',
                'icon' => 'ph-gear-six',
                'items' => [
                    ['label' => 'My Profile', 'icon' => 'ph-user-circle', 'url' => route('settings.profile'), 'active' => request()->routeIs('settings.profile*')],
                ],
            ];
        }
    }
@endphp

<nav class="kit-dashboard-nav" aria-label="Main navigation">
    @foreach ($primaryItems as $item)
        <a class="kit-dashboard-nav-link{{ $item['active'] ? ' is-active' : '' }}" href="{{ $item['url'] }}" title="{{ $item['label'] }}" @if ($item['active']) aria-current="page" @endif>
            <i class="ph {{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach

    @foreach ($sections as $section)
        @php
            $sectionKey = \Illuminate\Support\Str::slug($section['label']);
            $sectionActive = collect($section['items'])->contains(fn (array $item): bool => $item['active']);
        @endphp
        <details class="kit-dashboard-nav-group{{ $sectionActive ? ' is-active' : '' }}" data-sidebar-group="{{ $sectionKey }}" @if ($sectionActive) open @endif>
            <summary class="kit-dashboard-nav-group-toggle" title="{{ $section['label'] }}">
                <i class="ph {{ $section['icon'] }}" aria-hidden="true"></i>
                <span class="kit-dashboard-nav-group-label">{{ $section['label'] }}</span>
                <i class="ph ph-caret-down kit-dashboard-nav-group-chevron" aria-hidden="true"></i>
            </summary>
            <div class="kit-dashboard-nav-submenu">
                @foreach ($section['items'] as $item)
                    <a class="kit-dashboard-nav-link is-child{{ $item['active'] ? ' is-active' : '' }}" href="{{ $item['url'] }}" title="{{ $item['label'] }}" @if ($item['active']) aria-current="page" @endif>
                        <i class="ph {{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </details>
    @endforeach
</nav>

<div class="kit-dashboard-nav-logout">
    <a class="kit-dashboard-nav-link{{ request()->routeIs('settings.profile*') ? ' is-active' : '' }}" href="{{ route('settings.profile') }}" title="{{ $user->name }}" @if (request()->routeIs('settings.profile*')) aria-current="page" @endif>
        <i class="ph ph-user-circle" aria-hidden="true"></i>
        <span>{{ $user->name }}</span>
    </a>
    <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
        @csrf
        <button type="submit" class="kit-dashboard-nav-link" title="Log out">
            <i class="ph ph-sign-out" aria-hidden="true"></i>
            <span>Log out</span>
        </button>
    </form>
</div>
