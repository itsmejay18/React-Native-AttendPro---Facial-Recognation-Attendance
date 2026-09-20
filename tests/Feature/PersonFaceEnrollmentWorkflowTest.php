<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PersonFaceEnrollmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_staff_can_continue_from_person_registration_to_face_enrollment(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->post('/people', [
            'institution_id' => 'STUDENT-2026-001',
            'type' => 'student',
            'first_name' => 'Local',
            'last_name' => 'Operator',
            'email' => 'local.operator@example.test',
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
            'status' => 'active',
            'enroll_face' => '1',
        ]);

        $person = Person::query()->where('institution_id', 'STUDENT-2026-001')->firstOrFail();

        $response->assertRedirect(route('recognition', ['enroll' => $person->institution_id]).'#face-enrollment');

        $this->actingAs($staff)
            ->get(route('recognition', ['enroll' => $person->institution_id]))
            ->assertOk()
            ->assertSee('Enroll '.$person->full_name)
            ->assertSee('value="'.$person->institution_id.'"', false);
    }

    public function test_people_directory_has_a_direct_face_enrollment_action(): void
    {
        $staff = User::factory()->create();
        $person = Person::query()->create([
            'institution_id' => 'STUDENT-2026-001',
            'type' => 'student',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $this->actingAs($staff)
            ->get(route('people.index', ['type' => 'student']))
            ->assertOk()
            ->assertSee('Enroll face')
            ->assertSee(route('recognition', ['enroll' => $person->institution_id]), false);
    }

    public function test_people_are_registered_in_a_directory_modal(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('people.index', ['type' => 'student']))
            ->assertOk()
            ->assertSee('data-modal-open="#register-person-modal"', false)
            ->assertSee('id="register-person-modal"', false)
            ->assertSee('action="'.route('people.store').'"', false)
            ->assertSee('data-registration-enrollment', false)
            ->assertSee('data-registration-camera-video', false)
            ->assertSee('data-registration-enroll', false)
            ->assertSee('Create account & scan face', false)
            ->assertSee('Login account')
            ->assertDontSee('href="'.route('people.create', ['type' => 'student']).'"', false);

        $this->actingAs($staff)
            ->get(route('people.create', ['type' => 'faculty']))
            ->assertRedirect(route('people.index', ['type' => 'student']))
            ->assertSessionHas('open_modal', 'register-person-modal');
    }

    public function test_registration_modal_can_create_the_profile_without_leaving_the_directory(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->postJson(route('people.store'), [
            'institution_id' => 'STUDENT-2026-JSON',
            'type' => 'student',
            'first_name' => 'Camera',
            'last_name' => 'Enrollment',
            'email' => 'camera.enrollment@example.test',
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
            'status' => 'active',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.person.institution_id', 'STUDENT-2026-JSON')
            ->assertJsonPath('data.person.full_name', 'Camera Enrollment')
            ->assertJsonPath('data.person.type', 'student')
            ->assertJsonPath('data.redirect_url', route('people.index', ['type' => 'student']));

        $person = Person::query()->where('institution_id', 'STUDENT-2026-JSON')->firstOrFail();
        $account = User::query()->where('person_id', $person->id)->firstOrFail();
        $this->assertSame('camera.enrollment@example.test', $account->email);
        $this->assertTrue($account->hasRole('student'));
        $this->assertFalse($account->is_active);
        $this->assertTrue(Hash::check('StrongPass!2026', $account->password));
        $this->assertDatabaseMissing('facial_profiles', ['person_id' => $person->id]);
    }

    public function test_invalid_person_registration_reopens_the_modal_with_errors(): void
    {
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)
            ->followingRedirects()
            ->from(route('people.index', ['type' => 'student']))
            ->post(route('people.store'), [
                '_modal' => 'register-person-modal',
                'type' => 'student',
                'status' => 'active',
            ]);

        $response
            ->assertOk()
            ->assertSee('id="register-person-modal"', false)
            ->assertSee('data-modal-auto-open="true"', false)
            ->assertSee('Please review the information below.');
    }

    public function test_read_only_reviewer_cannot_submit_a_face_enrollment(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->post('/recognition/python/enroll')
            ->assertForbidden();
    }
}
