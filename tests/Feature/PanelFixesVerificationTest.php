<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PanelFixesVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $department = Department::query()->create(['code' => 'BSIT', 'name' => 'BSIT']);
        $location = Location::query()->create(['code' => 'COMLAB207', 'name' => 'COMLAB207']);
        $person = Person::query()->create([
            'institution_id' => '2026-00001', 'type' => 'student', 'first_name' => 'Test',
            'last_name' => 'Student', 'department_id' => $department->id, 'status' => 'active',
        ]);
        $schedule = Schedule::query()->create([
            'code' => 'CS101', 'name' => 'Intro to Computing', 'person_type' => 'student',
            'department_id' => $department->id, 'location_id' => $location->id,
            'days_of_week' => [1, 2, 3, 4, 5], 'starts_at' => '08:00', 'ends_at' => '09:00',
            'grace_minutes' => 10, 'checkout_required' => false, 'is_active' => true,
        ]);
        AttendanceRecord::query()->create([
            'uuid' => (string) Str::uuid(), 'person_id' => $person->id, 'schedule_id' => $schedule->id,
            'location_id' => $location->id, 'attendance_date' => today()->toDateString(),
            'time_in' => today()->setTime(8, 2), 'status' => 'present', 'source' => 'manual',
        ]);

        return [$department, $location, $person, $schedule];
    }

    public function test_attendance_tabs_and_filters_render(): void
    {
        $this->fixtures();
        $this->actingAs(User::factory()->create());

        $this->get('/attendance')
            ->assertOk()
            ->assertSee('data-tabs-scroll', false)
            ->assertSee('Attendance Sessions')
            ->assertSee('name="department_id"', false)
            ->assertSee('name="location_id"', false);
    }

    public function test_attendance_sessions_index_and_detail_render(): void
    {
        [$department, $location, $person, $schedule] = $this->fixtures();
        $this->actingAs(User::factory()->create());

        $this->get('/attendance/sessions')
            ->assertOk()
            ->assertSee('data-tabs-scroll', false)
            ->assertSee('SES-CS101-'.today()->format('Ymd'))
            ->assertSee('COMLAB207')
            ->assertSee('Active')
            ->assertSee('attendance/sessions/'.$schedule->id.'/'.today()->toDateString(), false);

        $this->get('/attendance/sessions/'.$schedule->id.'/'.today()->toDateString())
            ->assertOk()
            ->assertSee('Test')
            ->assertSee('2026-00001');

        $this->get('/attendance/sessions/9999/'.today()->toDateString())->assertNotFound();
    }

    public function test_reports_support_combined_filters_and_detail_table(): void
    {
        [$department, $location] = $this->fixtures();
        $this->actingAs(User::factory()->create());

        $uri = '/reports?department_id='.$department->id.'&location_id='.$location->id
            .'&date_from='.today()->toDateString().'&date_to='.today()->toDateString();

        $this->get($uri)
            ->assertOk()
            ->assertSee('Attendance outcomes')
            ->assertSee('Attendance rate')
            ->assertSee('Detailed records')
            ->assertSee('2026-00001')
            ->assertSee('COMLAB207');

        $this->get('/reports?preset=today')->assertOk()->assertSee('Attendance outcomes');
        $this->get('/reports?preset=week')->assertOk();
        $this->get('/reports?preset=month')->assertOk();

        $combo = '/reports?department_id='.$department->id.'&location_id='.$location->id
            .'&date_from='.today()->toDateString().'&date_to='.today()->toDateString()
            .'&status=present&search=2026-00001&sort=name';
        $this->get($combo)->assertOk()->assertSee('2026-00001');
    }

    public function test_attendance_staff_alias_and_permissions(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/attendance-staff')
            ->assertOk()
            ->assertSee('Attendance Staff')
            ->assertSee('data-password-toggle', false);

        $this->get('/dashboard')->assertOk()->assertSee('Attendance Staff');

        $this->actingAs(User::factory()->reviewer()->create());
        $this->get('/attendance-staff')->assertForbidden();
        $this->get('/attendance/sessions')->assertOk();
    }

    public function test_password_eye_buttons_present_on_auth_pages(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-password-toggle', false);
        $this->get('/register')->assertOk()->assertSee('data-password-toggle', false);
    }
}
