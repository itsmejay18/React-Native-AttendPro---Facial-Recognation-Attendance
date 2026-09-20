@php
    $attendanceTabs = [
        ['label' => 'Attendance Records', 'icon' => 'ph-folder-open', 'url' => route('attendance.index'), 'active' => request()->routeIs('attendance.index')],
        ['label' => 'Attendance Sessions', 'icon' => 'ph-calendar-check', 'url' => route('attendance.sessions'), 'active' => request()->routeIs('attendance.sessions*')],
        ['label' => 'Schedules', 'icon' => 'ph-clock', 'url' => route('schedules.index'), 'active' => request()->routeIs('schedules.*')],
    ];
@endphp

<div class="attendpro-tabs-scroll" role="navigation" aria-label="Attendance sections" data-tabs-scroll>
    <div class="attendpro-tabs" role="tablist">
        @foreach ($attendanceTabs as $tab)
            <a class="attendpro-tab{{ $tab['active'] ? ' is-active' : '' }}" role="tab" href="{{ $tab['url'] }}" @if ($tab['active']) aria-selected="true" @else aria-selected="false" @endif>
                <i class="ph {{ $tab['icon'] }}" aria-hidden="true"></i>{{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</div>
