@extends('layouts.dashboard')

@section('title', 'Reports & Analytics | AttendPro')
@section('page-title', 'Reports & Analytics')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head"><div><h2>Attendance analytics</h2><p>Daily, role, and department totals for {{ $from->format('M d, Y') }} through {{ $to->format('M d, Y') }}.</p></div><a class="kit-button secondary" href="{{ route('attendance.export', request()->query()) }}"><i class="ph ph-download-simple"></i>Export records</a></section>
        <div class="attendpro-filter-disclosure" data-filter-disclosure data-filter-open="{{ request()->hasAny(['date_from', 'date_to', 'department_id', 'location_id', 'status', 'search', 'sort', 'preset']) ? 'true' : 'false' }}">
            <div class="attendpro-filter-toolbar">
                <button class="kit-button secondary attendpro-filter-trigger" type="button" data-filter-trigger aria-expanded="false" aria-controls="report-filters"><i class="ph ph-funnel-simple"></i>Filters</button>
                @if (request()->hasAny(['date_from', 'date_to', 'department_id', 'location_id', 'status', 'search', 'sort', 'preset']))<span class="kit-badge info">Filters active</span>@endif
            </div>
        <form class="attendpro-filter-bar attendpro-filter-panel" id="report-filters" method="GET" data-filter-panel hidden>
            <div class="kit-field"><label>From</label><input name="date_from" type="date" value="{{ $filters['date_from'] ?? $from->toDateString() }}"></div>
            <div class="kit-field"><label>To</label><input name="date_to" type="date" value="{{ $filters['date_to'] ?? $to->toDateString() }}"></div>
            <div class="kit-field"><label>Department</label><select name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? request('department_id')) === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="kit-field"><label>Location</label><select name="location_id"><option value="">All locations</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? request('location_id')) === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="kit-field"><label>Status</label><select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? request('status')) === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="kit-field"><label>Search</label><input name="search" value="{{ $filters['search'] ?? request('search') }}" placeholder="ID or name"></div>
            <div class="kit-field"><label>Sort</label><select name="sort"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Student name</option><option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option></select></div>
            <div class="attendpro-filter-actions"><a class="kit-button ghost" href="{{ route('reports.index') }}">Clear</a><button class="kit-button primary" type="submit"><i class="ph ph-magnifying-glass"></i>Search</button></div>
        </form>
        </div>
        <div class="attendpro-presets" role="group" aria-label="Quick date ranges">
            <a class="attendpro-preset{{ $activePreset === 'today' ? ' is-active' : '' }}" href="{{ route('reports.index', array_merge(request()->except(['date_from', 'date_to', 'preset', 'page']), ['preset' => 'today'])) }}">Today</a>
            <a class="attendpro-preset{{ $activePreset === 'week' ? ' is-active' : '' }}" href="{{ route('reports.index', array_merge(request()->except(['date_from', 'date_to', 'preset', 'page']), ['preset' => 'week'])) }}">This week</a>
            <a class="attendpro-preset{{ $activePreset === 'month' ? ' is-active' : '' }}" href="{{ route('reports.index', array_merge(request()->except(['date_from', 'date_to', 'preset', 'page']), ['preset' => 'month'])) }}">This month</a>
            @if (request()->except('page'))
                <a class="attendpro-preset" href="{{ route('reports.index') }}">Clear filters</a>
            @endif
        </div>
        @php
            $makePie = function (array $items): array {
                $items = array_values(array_filter($items, fn ($item) => $item['value'] > 0));
                $sum = array_sum(array_column($items, 'value'));
                if ($sum === 0) return ['conic-gradient(#e5e7eb 0 100%)', 0, []];

                $cursor = 0;
                $segments = [];
                foreach ($items as $item) {
                    $end = $cursor + (($item['value'] / $sum) * 100);
                    $segments[] = "{$item['color']} {$cursor}% {$end}%";
                    $cursor = $end;
                }

                return ['conic-gradient('.implode(', ', $segments).')', $sum, $items];
            };
            $statusPie = $makePie([
                ['label' => 'Present', 'value' => (int) ($totals['present'] ?? 0), 'color' => '#22a06b'],
                ['label' => 'Late', 'value' => (int) ($totals['late'] ?? 0), 'color' => '#e8a317'],
                ['label' => 'Absent', 'value' => (int) ($totals['absent'] ?? 0), 'color' => '#e05252'],
            ]);
            $roleColors = ['#4f46e5', '#0ea5a8', '#d97706', '#db2777'];
            $rolePie = $makePie(collect($byRole)->map(fn ($count, $role) => [
                'label' => ucfirst($role), 'value' => (int) $count, 'color' => $roleColors[count($roleColors) ? array_search($role, array_keys($byRole->all()), true) % count($roleColors) : 0],
            ])->values()->all());
            $departmentColors = ['#4f46e5', '#0ea5a8', '#e8a317', '#db2777', '#22a06b', '#64748b'];
            $departmentPie = $makePie(collect($byDepartment)->map(function ($count, $department) use ($departmentColors) {
                static $index = 0;
                return ['label' => $department, 'value' => (int) $count, 'color' => $departmentColors[$index++ % count($departmentColors)]];
            })->values()->all());
            $rateGradient = $total > 0
                ? "conic-gradient(#22a06b 0 {$attendanceRate}%, #e5e7eb {$attendanceRate}% 100%)"
                : 'conic-gradient(#e5e7eb 0 100%)';
        @endphp
        <section class="attendpro-report-chart-grid" aria-label="Attendance chart summaries">
            <article class="kit-dashboard-panel attendpro-report-chart">
                <div><p class="kit-dashboard-stat-label">Attendance outcomes</p><h3>{{ number_format($statusPie[1]) }} records</h3><p class="kit-muted">Present, late, and absent records.</p></div>
                <div class="attendpro-report-donut" style="--chart-background: {{ $statusPie[0] }}" role="img" aria-label="Attendance outcomes chart"><strong>{{ number_format($statusPie[1]) }}</strong><span>Total</span></div>
                <ul class="attendpro-report-legend">@forelse($statusPie[2] as $item)<li><i style="--legend-color: {{ $item['color'] }}"></i><span>{{ $item['label'] }}</span><strong>{{ number_format($item['value']) }}</strong></li>@empty<li class="is-empty">No records in this range.</li>@endforelse</ul>
            </article>
            <article class="kit-dashboard-panel attendpro-report-chart">
                <div><p class="kit-dashboard-stat-label">Attendance rate</p><h3>{{ $attendanceRate }}%</h3><p class="kit-muted">Present and late of all filtered records.</p></div>
                <div class="attendpro-report-donut" style="--chart-background: {{ $rateGradient }}" role="img" aria-label="{{ $attendanceRate }} percent attendance rate"><strong>{{ $attendanceRate }}%</strong><span>Rate</span></div>
                <ul class="attendpro-report-legend"><li><i style="--legend-color: #22a06b"></i><span>Recorded</span><strong>{{ number_format(($totals['present'] ?? 0) + ($totals['late'] ?? 0)) }}</strong></li><li><i style="--legend-color: #e5e7eb"></i><span>Not recorded</span><strong>{{ number_format($totals['absent'] ?? 0) }}</strong></li></ul>
            </article>
            <article class="kit-dashboard-panel attendpro-report-chart">
                <div><p class="kit-dashboard-stat-label">By user group</p><h3>{{ number_format($rolePie[1]) }} records</h3><p class="kit-muted">Attendance records by person role.</p></div>
                <div class="attendpro-report-donut" style="--chart-background: {{ $rolePie[0] }}" role="img" aria-label="Attendance by user group chart"><strong>{{ number_format($rolePie[1]) }}</strong><span>Roles</span></div>
                <ul class="attendpro-report-legend">@forelse($rolePie[2] as $item)<li><i style="--legend-color: {{ $item['color'] }}"></i><span>{{ $item['label'] }}</span><strong>{{ number_format($item['value']) }}</strong></li>@empty<li class="is-empty">No role data in this range.</li>@endforelse</ul>
            </article>
            <article class="kit-dashboard-panel attendpro-report-chart">
                <div><p class="kit-dashboard-stat-label">By department</p><h3>{{ number_format($departmentPie[1]) }} records</h3><p class="kit-muted">Attendance records by department.</p></div>
                <div class="attendpro-report-donut" style="--chart-background: {{ $departmentPie[0] }}" role="img" aria-label="Attendance by department chart"><strong>{{ number_format($departmentPie[1]) }}</strong><span>Groups</span></div>
                <ul class="attendpro-report-legend">@forelse($departmentPie[2] as $item)<li><i style="--legend-color: {{ $item['color'] }}"></i><span>{{ $item['label'] }}</span><strong>{{ number_format($item['value']) }}</strong></li>@empty<li class="is-empty">No department data in this range.</li>@endforelse</ul>
            </article>
        </section>
        <section class="kit-dashboard-panel"><div class="kit-dashboard-panel-head"><h3>Daily records</h3></div><div class="attendpro-bar-list">@forelse($daily as $date => $count)<div><span>{{ \Carbon\Carbon::parse($date)->format('M d') }}</span><i style="--bar-width: {{ min(100, $daily->max() ? ($count / $daily->max()) * 100 : 0) }}%"></i><strong>{{ $count }}</strong></div>@empty<p class="attendpro-empty">No daily records available.</p>@endforelse</div></section>
        <section class="kit-dashboard-panel">
            <div class="kit-dashboard-panel-head"><h3>Detailed records</h3><span>{{ number_format($records->total()) }} matching {{ \Illuminate\Support\Str::plural('record', $records->total()) }}</span></div>
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead><tr><th>Student ID</th><th>Student name</th><th>Department</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Date</th><th>Time in</th><th>Time out</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td><strong>{{ $record->person->institution_id }}</strong></td>
                            <td>{{ $record->person->full_name }}</td>
                            <td>{{ $record->person->department?->name ?? 'Unassigned' }}</td>
                            <td>{{ $record->schedule?->name ?? '—' }}</td>
                            <td>{{ $record->schedule?->department?->name ?? '—' }}</td>
                            <td>{{ $record->location?->name ?? '—' }}</td>
                            <td>{{ $record->attendance_date->format('M d, Y') }}</td>
                            <td>{{ $record->time_in?->format('h:i:s A') ?? '—' }}</td>
                            <td>{{ $record->time_out?->format('h:i:s A') ?? '—' }}</td>
                            <td><span class="kit-badge {{ $record->status === 'present' ? 'success' : ($record->status === 'late' ? 'warning' : 'danger') }}">{{ ucfirst($record->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="attendpro-empty">No attendance records found for the selected filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $records->links() }}</div>
        </section>
    </div>
@endsection
