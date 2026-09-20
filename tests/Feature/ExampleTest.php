<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_attendpro_public_page_is_available(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('One secure attendance system for everyone on campus.')
            ->assertSeeInOrder(['Students', 'Faculty', 'Staff', 'Administrators'])
            ->assertSee('attendpro-theme-light', false)
            ->assertSee('attendpro-hero-image', false)
            ->assertDontSee('data-theme-toggle', false)
            ->assertDontSee('data-slideshow', false)
            ->assertDontSee('attendpro-slideshow-controls', false);
    }

    public function test_the_attendpro_ui_pages_are_available(): void
    {
        $user = User::factory()->create();

        $this->get('/dashboard')
            ->assertRedirect('/login');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Attendance Overview')
            ->assertSee('kit-dashboard-scroll', false)
            ->assertSee('data-sidebar-group="attendance-operations"', false)
            ->assertSee('data-sidebar-group="people-directory"', false)
            ->assertSee('data-sidebar-group="insights-oversight"', false)
            ->assertSee('data-sidebar-group="settings"', false)
            ->assertSeeInOrder(['Students', 'Present Today'])
            ->assertDontSee('Faculty records')
            ->assertDontSee('Staff records');

        $this->get('/ui-kit')
            ->assertOk()
            ->assertSee('AttendPro Design System');

        $this->get('/ui-kit/components')
            ->assertOk()
            ->assertSee('AttendPro UI components');
    }

    public function test_the_recognition_center_is_available(): void
    {
        config(['attendpro.python_service.managed' => true]);

        $this->actingAs(User::factory()->create())->get('/recognition')
            ->assertOk()
            ->assertSee('Recognition Center')
            ->assertSee('Recognition Server')
            ->assertDontSee('data-python-server-panel', false)
            ->assertDontSee('Start recognition server')
            ->assertSee('Start Camera')
            ->assertSee('Scan Face')
            ->assertSee('id="recognition-result-modal"', false)
            ->assertSee('data-result-profile', false)
            ->assertDontSee('LATEST CAPTURE');
    }

    public function test_the_recognition_server_has_a_separate_settings_page(): void
    {
        config(['attendpro.python_service.managed' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/settings/recognition-server')
            ->assertOk()
            ->assertSee('Local Recognition Server')
            ->assertSee('data-python-server-panel', false)
            ->assertSee('Start recognition server')
            ->assertSee('Refresh status')
            ->assertSee('Open Recognition Center');
    }

    public function test_the_global_loading_overlay_uses_the_school_favicon(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('attendpro-loading-overlay', false)
            ->assertSee('attendpro-loading-logo', false)
            ->assertSee('Holy Child College of Davao is loading');
    }

    public function test_the_header_has_no_user_dropdown_and_profile_is_available_in_settings(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response
            ->assertOk()
            ->assertDontSee('data-user-menu-toggle', false)
            ->assertDontSee('data-user-menu-panel', false)
            ->assertSee('My Profile');

        $this->get(route('settings.profile'))
            ->assertOk()
            ->assertSee('My profile')
            ->assertSee('Change password');
    }

    public function test_authenticated_management_pages_render(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/people?type=student', '/attendance', '/schedules', '/reports', '/audit-logs', '/users'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_authenticated_create_actions_are_presented_as_modals(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/people?type=student')
            ->assertOk()
            ->assertSee('data-modal-open="#register-person-modal"', false)
            ->assertSee('id="register-person-modal"', false);

        $this->get('/schedules')
            ->assertOk()
            ->assertSee('data-modal-open="#schedule-modal"', false)
            ->assertSee('data-modal-open="#department-modal"', false)
            ->assertSee('data-modal-open="#location-modal"', false)
            ->assertSee('id="department-modal"', false)
            ->assertSee('id="location-modal"', false)
            ->assertDontSee('attendpro-management-grid', false);

        $this->get('/attendance')->assertSee('id="manual-attendance-modal"', false);
        $this->get('/users')->assertSee('id="user-modal"', false);
    }

    public function test_reviewer_sidebar_only_renders_personal_profile_in_settings(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->get('/dashboard')
            ->assertOk()
            ->assertSee('Attendance Operations')
            ->assertSee('People Directory')
            ->assertSee('Insights &amp; Oversight', false)
            ->assertSee('data-sidebar-group="settings"', false)
            ->assertSee('My Profile')
            ->assertDontSee('Recognition Server')
            ->assertDontSee('User Management');

        $this->actingAs($reviewer)
            ->get('/settings/recognition-server')
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->get(route('settings.profile'))
            ->assertOk();
    }
}
