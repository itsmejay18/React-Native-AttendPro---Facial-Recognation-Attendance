@extends('layouts.dashboard')

@section('title', 'Attendance Staff | AttendPro')
@section('page-title', 'Attendance Staff')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div>
                <h2>Authorized administrators and staff</h2>
                <p>Create operator accounts, assign access levels, and control access to protected attendance data.</p>
            </div>
            <button class="kit-button primary" type="button" data-modal-open="#user-modal">
                <i class="ph ph-user-plus"></i>Add user
            </button>
        </section>

        <section class="kit-dashboard-panel">
            <div class="kit-table-wrap">
                <table class="kit-table">
                    <thead>
                        <tr><th>User</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            @php($role = $managedUser->getRoleNames()->first() ?? 'reviewer')
                            <tr>
                                <td><strong>{{ $managedUser->name }}</strong><br><span class="kit-muted">{{ $managedUser->email }}</span></td>
                                <td>{{ $role === 'attendance_admin' ? 'Attendance staff' : str($role)->replace('_', ' ')->title() }}</td>
                                <td>
                                    <span class="kit-badge {{ $managedUser->is_active ? 'success' : 'warning' }}">
                                        {{ $managedUser->is_active ? 'Active' : 'Pending / inactive' }}
                                    </span>
                                </td>
                                <td>{{ $managedUser->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>{{ $managedUser->created_at->format('M d, Y') }}</td>
                                <td>
                                    <button class="kit-button ghost attendpro-button-sm" type="button" data-modal-open="#edit-user-{{ $managedUser->id }}">
                                        <i class="ph ph-pencil-simple"></i>Edit
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="attendpro-empty" colspan="6">No user accounts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="attendpro-pagination">{{ $users->links() }}</div>
        </section>
    </div>
@endsection

@push('modals')
    @php($userFailedModal = $errors->any() ? old('_modal') : null)
    <div class="kit-modal" id="user-modal" role="dialog" aria-modal="true" aria-labelledby="user-title" data-modal-auto-open="{{ $userFailedModal === 'user-modal' ? 'true' : 'false' }}">
        <form class="kit-modal-panel" method="POST" action="{{ route('users.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="user-modal">
            <div class="kit-modal-head">
                <div><strong id="user-title">Create operator account</strong><span class="attendpro-modal-subtitle">Add an authorized administrator or attendance staff account.</span></div>
                <button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button>
            </div>
            <div class="kit-modal-body attendpro-form-stack">
                <x-modal-errors modal="user-modal" />
                <div class="kit-field"><label for="new-user-name">Full name</label><input id="new-user-name" name="name" value="{{ $userFailedModal === 'user-modal' ? old('name') : '' }}" required></div>
                <div class="kit-field"><label for="new-user-email">Email</label><input id="new-user-email" name="email" type="email" value="{{ $userFailedModal === 'user-modal' ? old('email') : '' }}" required></div>
                <div class="kit-field">
                    <label for="new-user-role">Role</label>
                    <select id="new-user-role" name="role">
                        <option value="attendance_admin" @selected($userFailedModal !== 'user-modal' || old('role') === 'attendance_admin')>Attendance staff</option>
                        <option value="reviewer" @selected($userFailedModal === 'user-modal' && old('role') === 'reviewer')>Reviewer (read only)</option>
                        <option value="super_admin" @selected($userFailedModal === 'user-modal' && old('role') === 'super_admin')>Super administrator</option>
                    </select>
                </div>
                <div class="kit-field"><label for="new-user-password">Password (minimum 12 characters)</label><div class="attendpro-password-wrap"><input id="new-user-password" name="password" type="password" minlength="12" required><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div></div>
                <div class="kit-field"><label for="new-user-password-confirmation">Confirm password</label><div class="attendpro-password-wrap"><input id="new-user-password-confirmation" name="password_confirmation" type="password" minlength="12" required><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div></div>
                <input type="hidden" name="is_active" value="1">
            </div>
            <div class="kit-modal-foot">
                <button class="kit-button ghost" type="button" data-modal-close>Cancel</button>
                <button class="kit-button primary" type="submit">Create account</button>
            </div>
        </form>
    </div>

    @foreach ($users as $managedUser)
        @php($role = $managedUser->getRoleNames()->first() ?? 'reviewer')
        @php($editUserModal = 'edit-user-'.$managedUser->id)
        @php($editUserFailed = $userFailedModal === $editUserModal)
        <div class="kit-modal" id="{{ $editUserModal }}" role="dialog" aria-modal="true" aria-labelledby="edit-user-title-{{ $managedUser->id }}" data-modal-auto-open="{{ $editUserFailed ? 'true' : 'false' }}">
            <form class="kit-modal-panel" method="POST" action="{{ route('users.update', $managedUser) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="{{ $editUserModal }}">
                <div class="kit-modal-head">
                    <strong id="edit-user-title-{{ $managedUser->id }}">Edit {{ $managedUser->name }}</strong>
                    <button class="kit-close" type="button" data-modal-close aria-label="Close"><i class="ph ph-x"></i></button>
                </div>
                <div class="kit-modal-body attendpro-form-stack">
                    <x-modal-errors :modal="$editUserModal" />
                    <div class="kit-field"><label for="user-name-{{ $managedUser->id }}">Full name</label><input id="user-name-{{ $managedUser->id }}" name="name" value="{{ $editUserFailed ? old('name') : $managedUser->name }}" required></div>
                    <div class="kit-field"><label for="user-email-{{ $managedUser->id }}">Email</label><input id="user-email-{{ $managedUser->id }}" name="email" type="email" value="{{ $editUserFailed ? old('email') : $managedUser->email }}" required></div>
                    <div class="kit-field">
                        <label for="user-role-{{ $managedUser->id }}">Role</label>
                        <select id="user-role-{{ $managedUser->id }}" name="role">
                            <option value="attendance_admin" @selected(($editUserFailed ? old('role') : $role) === 'attendance_admin')>Attendance staff</option>
                            <option value="reviewer" @selected(($editUserFailed ? old('role') : $role) === 'reviewer')>Reviewer (read only)</option>
                            <option value="super_admin" @selected(($editUserFailed ? old('role') : $role) === 'super_admin')>Super administrator</option>
                        </select>
                    </div>
                    <div class="kit-field">
                        <label for="user-status-{{ $managedUser->id }}">Account status</label>
                        <select id="user-status-{{ $managedUser->id }}" name="is_active">
                            <option value="1" @selected((string) ($editUserFailed ? old('is_active') : (int) $managedUser->is_active) === '1')>Active</option>
                            <option value="0" @selected((string) ($editUserFailed ? old('is_active') : (int) $managedUser->is_active) === '0')>Pending / inactive</option>
                        </select>
                    </div>
                    <div class="kit-field"><label for="user-password-{{ $managedUser->id }}">New password (optional)</label><div class="attendpro-password-wrap"><input id="user-password-{{ $managedUser->id }}" name="password" type="password" minlength="12" autocomplete="new-password"><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div></div>
                    <div class="kit-field"><label for="user-password-confirmation-{{ $managedUser->id }}">Confirm new password</label><div class="attendpro-password-wrap"><input id="user-password-confirmation-{{ $managedUser->id }}" name="password_confirmation" type="password" minlength="12" autocomplete="new-password"><button class="attendpro-password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="ph ph-eye" aria-hidden="true"></i></button></div></div>
                </div>
                <div class="kit-modal-foot">
                    <button class="kit-button ghost" type="button" data-modal-close>Cancel</button>
                    <button class="kit-button primary" type="submit">Save changes</button>
                </div>
            </form>
        </div>
    @endforeach
@endpush
