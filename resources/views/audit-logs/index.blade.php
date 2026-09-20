@extends('layouts.dashboard')

@section('title', 'Activity Logs | AttendPro')
@section('page-title', 'Activity Logs')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head"><div><h2>Auditable system activity</h2><p>Administrative record changes include the operator, origin, and before/after values.</p></div><span class="kit-dashboard-tag kit-dashboard-tag-blue">Immutable history</span></section>
        <div class="attendpro-filter-disclosure" data-filter-disclosure data-filter-open="{{ request()->hasAny(['action', 'search']) ? 'true' : 'false' }}">
            <div class="attendpro-filter-toolbar">
                <button class="kit-button secondary attendpro-filter-trigger" type="button" data-filter-trigger aria-expanded="false" aria-controls="audit-log-filters"><i class="ph ph-funnel-simple"></i>Filters</button>
                @if (request()->hasAny(['action', 'search']))<span class="kit-badge info">Filters active</span>@endif
            </div>
            <form class="attendpro-filter-bar attendpro-filter-panel" id="audit-log-filters" method="GET" data-filter-panel hidden>
                <div class="kit-field"><label>Action</label><select name="action"><option value="">All</option>@foreach(['created','updated','deleted'] as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ ucfirst($action) }}</option>@endforeach</select></div>
                <div class="kit-field"><label>Search</label><input name="search" value="{{ request('search') }}" placeholder="Model or administrator"></div>
                <div class="attendpro-filter-actions"><a class="kit-button ghost" href="{{ route('audit-logs.index') }}">Clear</a><button class="kit-button primary" type="submit"><i class="ph ph-magnifying-glass"></i>Search</button></div>
            </form>
        </div>
        <section class="kit-dashboard-panel"><div class="kit-table-wrap"><table class="kit-table">
            <thead><tr><th>Time</th><th>Operator</th><th>Action</th><th>Record</th><th>Origin</th><th>Changes</th></tr></thead>
            <tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at->format('M d, Y h:i:s A') }}</td><td>{{ $log->user?->name ?? 'System / API' }}</td><td><span class="kit-badge {{ $log->action === 'created' ? 'success' : ($log->action === 'deleted' ? 'danger' : 'warning') }}">{{ ucfirst($log->action) }}</span></td><td>{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td><td>{{ $log->ip_address ?? 'Console' }}</td><td><details><summary>View JSON</summary><pre class="attendpro-json">{{ json_encode(['before' => $log->before, 'after' => $log->after], JSON_PRETTY_PRINT) }}</pre></details></td></tr>@empty<tr><td colspan="6" class="attendpro-empty">No audited activity found.</td></tr>@endforelse</tbody>
        </table></div><div class="attendpro-pagination">{{ $logs->links() }}</div></section>
    </div>
@endsection
