<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampusPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_campus_role_can_login_but_only_sees_its_personal_portal(): void
    {
        foreach (Person::TYPES as $type) {
            $person = Person::query()->create([
                'institution_id' => strtoupper($type).'-LOGIN-001',
                'type' => $type,
                'first_name' => ucfirst($type),
                'last_name' => 'Portal User',
                'email' => $type.'.portal@example.test',
                'status' => 'active',
            ]);
            $user = User::query()->create([
                'person_id' => $person->id,
                'name' => $person->full_name,
                'email' => $person->email,
                'password' => 'StrongPass!2026',
                'is_active' => true,
            ]);
            $user->assignRole(Role::findOrCreate($type, 'web'));

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Only your own records are shown here.')
                ->assertSee($person->institution_id)
                ->assertDontSee('People Directory');

            $this->actingAs($user)->get(route('portal.attendance'))->assertOk();
            $this->actingAs($user)->get('/people')->assertForbidden();
            $this->actingAs($user)->get('/reports')->assertForbidden();
            $this->actingAs($user)->get('/users')->assertForbidden();
        }
    }
}
