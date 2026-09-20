<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_submit_a_pending_reviewer_registration(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Register for AttendPro')
            ->assertSee('require administrator approval');

        $response = $this->post('/register', [
            'name' => 'Pending Reviewer',
            'email' => 'reviewer@hccd.edu.ph',
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $user = User::query()->where('email', 'reviewer@hccd.edu.ph')->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->hasRole('reviewer'));
    }

    public function test_an_active_account_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@hccd.edu.ph',
            'password' => 'StrongPass!2026',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPass!2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'password' => 'StrongPass!2026',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPass!2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_reviewer_can_read_records_but_cannot_manage_users(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->get('/attendance')->assertOk();
        $this->actingAs($reviewer)->get('/users')->assertForbidden();
    }

    public function test_an_administrator_can_approve_a_pending_registration(): void
    {
        $administrator = User::factory()->create();
        $pending = User::query()->create([
            'name' => 'Pending Reviewer',
            'email' => 'pending@hccd.edu.ph',
            'password' => 'StrongPass!2026',
            'is_active' => false,
        ]);
        $pending->assignRole('reviewer');

        $this->actingAs($administrator)->put('/users/'.$pending->id, [
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => 'reviewer',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue($pending->fresh()->is_active);
        $this->assertTrue($pending->fresh()->hasRole('reviewer'));
    }

    public function test_a_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
