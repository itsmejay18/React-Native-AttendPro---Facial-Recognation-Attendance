@extends('layouts.dashboard')

@section('title', 'Recognition Terminals | AttendPro')
@section('page-title', 'Recognition Terminals')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div><h2>Authorized camera clients</h2><p>Each Python station uses its own revocable token and reports its health to Laravel.</p></div>
            <button class="kit-button primary" type="button" data-modal-open="#terminal-modal"><i class="ph ph-plus"></i>Add terminal</button>
        </section>

        @if (session('terminal_token'))
            <section class="attendpro-secret-panel" role="alert">
                <div><strong>Token for {{ session('terminal_name') }}</strong><p>Copy this value into the Python app’s environment now. Only its hash is stored.</p></div>
                <code>{{ session('terminal_token') }}</code>
            </section>
        @endif

        <section class="kit-dashboard-panel">
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead><tr><th>Terminal</th><th>Location</th><th>Status</th><th>Last heartbeat</th><th>Client</th><th>Token</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse ($terminals as $terminal)
                        <tr>
                            <td><strong>{{ $terminal->name }}</strong><br><span class="kit-muted">{{ $terminal->code }} · {{ $terminal->uuid }}</span></td>
                            <td>{{ $terminal->location->name }}</td>
                            <td><span class="kit-badge {{ $terminal->status === 'online' && $terminal->is_active ? 'success' : 'danger' }}">{{ $terminal->is_active ? ucfirst($terminal->status) : 'Disabled' }}</span></td>
                            <td>{{ $terminal->last_seen_at?->diffForHumans() ?? 'Never' }}<br><span class="kit-muted">{{ $terminal->last_ip ?? 'No IP' }}</span></td>
                            <td>{{ $terminal->app_version ?? '—' }}</td>
                            <td>{{ $terminal->api_token_id ? 'Issued' : 'Missing' }}</td>
                            <td>
                                <form method="POST" action="{{ route('terminals.rotate', $terminal) }}" onsubmit="return confirm('Rotate this token? The current Python client will disconnect.')">@csrf<button class="kit-button ghost attendpro-button-sm" type="submit">Rotate token</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="attendpro-empty">No recognition terminals are registered.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $terminals->links() }}</div>
        </section>

        <section class="kit-dashboard-panel attendpro-api-note">
            <i class="ph ph-plugs-connected"></i>
            <div><h3>Python backend connection</h3><p>Set <code>ATTENDPRO_LARAVEL_API_URL={{ rtrim(config('app.url'), '/') }}/api/v1</code> and <code>ATTENDPRO_TERMINAL_TOKEN=&lt;token&gt;</code> in the repository <code>.env</code>. The browser never receives this token; the Python service uses it for profile synchronization and attendance submission.</p></div>
        </section>
    </div>
@endsection

@push('modals')
    @php($terminalModalFailed = old('_modal') === 'terminal-modal' && $errors->any())
    <div class="kit-modal" id="terminal-modal" role="dialog" aria-modal="true" aria-labelledby="terminal-title" data-modal-auto-open="{{ $terminalModalFailed ? 'true' : 'false' }}">
        <form class="kit-modal-panel" method="POST" action="{{ route('terminals.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="terminal-modal">
            <div class="kit-modal-head"><div><strong id="terminal-title">Register recognition terminal</strong><span class="attendpro-modal-subtitle">Authorize a camera station and issue its connection token.</span></div><button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button></div>
            <div class="kit-modal-body attendpro-form-stack">
                <x-modal-errors modal="terminal-modal" />
                <div class="kit-field"><label for="terminal-code">Terminal code</label><input id="terminal-code" name="code" value="{{ $terminalModalFailed ? old('code') : '' }}" placeholder="MAIN-GATE-01" required></div>
                <div class="kit-field"><label for="terminal-name">Display name</label><input id="terminal-name" name="name" value="{{ $terminalModalFailed ? old('name') : '' }}" placeholder="Main Entrance Camera" required></div>
                <div class="kit-field"><label for="terminal-location">Location</label><select id="terminal-location" name="location_id" required><option value="">Choose a location</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected($terminalModalFailed && (string) old('location_id') === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
                <label class="attendpro-check"><input type="checkbox" name="can_enroll" value="1" @checked($terminalModalFailed && old('can_enroll'))> Allow this station to enroll or replace facial embeddings</label>
            </div>
            <div class="kit-modal-foot"><button class="kit-button ghost" type="button" data-modal-close>Cancel</button><button class="kit-button primary" type="submit">Create and issue token</button></div>
        </form>
    </div>
@endpush
