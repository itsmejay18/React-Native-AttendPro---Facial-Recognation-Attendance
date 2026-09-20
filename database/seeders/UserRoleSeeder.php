<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'view dashboard',
        'view recognition',
        'manage recognition',
        'view people',
        'manage people',
        'view attendance',
        'manage attendance',
        'view schedules',
        'manage schedules',
        'view reports',
        'view audit logs',
        'manage terminals',
        'manage users',
        'view own profile',
        'view own attendance',
    ];

    /**
     * @var list<string>
     */
    private const REVIEWER_PERMISSIONS = [
        'view dashboard',
        'view recognition',
        'view people',
        'view attendance',
        'view schedules',
        'view reports',
        'view audit logs',
    ];

    /**
     * @var list<string>
     */
    private const CAMPUS_USER_PERMISSIONS = [
        'view dashboard',
        'view own profile',
        'view own attendance',
    ];

    /**
     * Seed every system role and permission, plus the configured operator accounts.
     */
    public function run(): void
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $permissionRegistrar->forgetCachedPermissions();

        $roles = [
            'super_admin' => Role::findOrCreate('super_admin', 'web'),
            'attendance_admin' => Role::findOrCreate('attendance_admin', 'web'),
            'reviewer' => Role::findOrCreate('reviewer', 'web'),
            'student' => Role::findOrCreate('student', 'web'),
            'faculty' => Role::findOrCreate('faculty', 'web'),
            'staff' => Role::findOrCreate('staff', 'web'),
        ];

        $roles['super_admin']->syncPermissions(self::PERMISSIONS);
        $roles['attendance_admin']->syncPermissions(self::PERMISSIONS);
        $roles['reviewer']->syncPermissions(self::REVIEWER_PERMISSIONS);
        $roles['student']->syncPermissions(self::CAMPUS_USER_PERMISSIONS);
        $roles['faculty']->syncPermissions(self::CAMPUS_USER_PERMISSIONS);
        $roles['staff']->syncPermissions(self::CAMPUS_USER_PERMISSIONS);

        $users = config('attendpro.seed_users');

        foreach ($users as $role => $attributes) {
            if (blank($attributes['password']) && app()->environment('production')) {
                throw new RuntimeException("Set {$attributes['password_environment_variable']} before running the production seeder.");
            }

            $user = User::query()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'password' => $attributes['password'] ?: $attributes['local_password'],
                    'is_active' => true,
                ],
            );

            $user->syncRoles($roles[$role]);
        }

        $permissionRegistrar->forgetCachedPermissions();
    }
}
