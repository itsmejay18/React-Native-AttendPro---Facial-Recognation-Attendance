<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            // Campus accounts are managed with their linked person profile in People Directory.
            'users' => User::query()->whereNull('person_id')->with('roles')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $role = $validated['role'];
        unset($validated['role']);

        DB::transaction(function () use ($request, $validated, $role): void {
            $user = User::query()->create($validated);
            $user->syncRoles($role);
            $this->auditRoleChange($request, $user, [], [$role]);
        });

        return back()->with('success', 'Operator account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validated($request, $user);
        $role = $validated['role'];
        unset($validated['role']);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        if ($request->user()->is($user) && ! ($validated['is_active'] ?? true)) {
            return back()->withInput()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }
        DB::transaction(function () use ($request, $user, $validated, $role): void {
            $before = $user->getRoleNames()->values()->all();

            $user->update($validated);
            $user->syncRoles($role);

            if ($before !== [$role]) {
                $this->auditRoleChange($request, $user, $before, [$role]);
            }
        });

        return back()->with('success', 'Operator account updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(['super_admin', 'attendance_admin', 'reviewer'])],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function auditRoleChange(Request $request, User $user, array $before, array $after): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'roles_updated',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'before' => ['roles' => $before],
            'after' => ['roles' => $after],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
