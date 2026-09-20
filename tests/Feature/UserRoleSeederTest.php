<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_every_role_permission_and_default_user_idempotently(): void
    {
        config()->set('attendpro.seed_users', [
            'super_admin' => $this->seedUser('System Administrator', 'admin@example.test', 'AdminPassword!2026'),
            'attendance_admin' => $this->seedUser('Attendance Staff', 'staff@example.test', 'StaffPassword!2026'),
            'reviewer' => $this->seedUser('Attendance Reviewer', 'reviewer@example.test', 'ReviewerPassword!2026'),
        ]);

        $this->seed(UserRoleSeeder::class);
        $this->seed(UserRoleSeeder::class);

        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('permissions', 15);
        $this->assertDatabaseCount('users', 3);

        $this->assertSeededUser('admin@example.test', 'AdminPassword!2026', 'super_admin');
        $this->assertSeededUser('staff@example.test', 'StaffPassword!2026', 'attendance_admin');
        $this->assertSeededUser('reviewer@example.test', 'ReviewerPassword!2026', 'reviewer');

        $this->assertCount(15, Role::findByName('super_admin')->permissions);
        $this->assertCount(15, Role::findByName('attendance_admin')->permissions);
        $this->assertEqualsCanonicalizing([
            'view dashboard',
            'view recognition',
            'view people',
            'view attendance',
            'view schedules',
            'view reports',
            'view audit logs',
        ], Role::findByName('reviewer')->permissions->pluck('name')->all());
        foreach (['student', 'faculty', 'staff'] as $role) {
            $this->assertEqualsCanonicalizing([
                'view dashboard',
                'view own profile',
                'view own attendance',
            ], Role::findByName($role)->permissions->pluck('name')->all());
        }
    }

    /**
     * @return array<string, string>
     */
    private function seedUser(string $name, string $email, string $password): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'local_password' => 'unused-local-password',
            'password_environment_variable' => 'UNUSED_PASSWORD',
        ];
    }

    private function assertSeededUser(string $email, string $password, string $role): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole($role));
        $this->assertTrue(Hash::check($password, $user->password));
    }
}
