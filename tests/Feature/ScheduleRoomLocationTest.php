<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use App\Models\Terminal;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\ScheduleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleRoomLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_classroom_schedule_with_a_typed_room(): void
    {
        $this->actingAs(User::factory()->create());
        $department = Department::query()->create(['code' => 'IT', 'name' => 'Information Technology']);

        $this->post(route('schedules.store'), [
            'code' => 'IT312',
            'name' => 'IT 312',
            'person_type' => 'student',
            'department_id' => $department->id,
            'program' => Person::PROGRAMS[1],
            'location_type' => 'computer_laboratory',
            'room_code' => '  COMLAB-2  ',
            'days_of_week' => [1],
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'grace_minutes' => 10,
        ])->assertRedirect();

        $schedule = Schedule::query()->where('code', 'IT312')->firstOrFail();

        $this->assertSame('computer_laboratory', $schedule->location_type);
        $this->assertSame('COMLAB-2', $schedule->room_code);
        $this->assertSame(Person::PROGRAMS[1], $schedule->program);
        $this->assertNotNull($schedule->location_id);
        $this->assertSame('Computer Laboratory - COMLAB-2', $schedule->room_display);
        // Room materialized on demand through the existing locations table.
        $this->assertDatabaseHas('locations', ['code' => 'COMLAB-2']);
    }

    public function test_room_code_is_required_for_classroom_location_types(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('schedules.store'), [
            'code' => 'IT313',
            'name' => 'IT 313',
            'location_type' => 'classroom',
            'room_code' => '   ',
            'days_of_week' => [1],
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'grace_minutes' => 10,
        ])->assertSessionHasErrors('room_code');

        $this->assertDatabaseMissing('schedules', ['code' => 'IT313']);
    }

    public function test_any_location_schedule_keeps_existing_behavior(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('schedules.store'), [
            'code' => 'OPEN',
            'name' => 'Open Schedule',
            'location_type' => 'any',
            'days_of_week' => [1, 2, 3, 4, 5],
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'grace_minutes' => 10,
        ])->assertRedirect();

        $schedule = Schedule::query()->where('code', 'OPEN')->firstOrFail();

        $this->assertTrue($schedule->isAnyLocation());
        $this->assertNull($schedule->location_id);
        $this->assertNull($schedule->room_code);
    }

    public function test_room_suggestions_include_standard_and_previously_used_rooms(): void
    {
        $this->actingAs(User::factory()->create());
        Schedule::query()->create([
            'code' => 'A', 'name' => 'A', 'location_type' => 'computer_laboratory',
            'room_code' => 'COMLAB-2', 'days_of_week' => [1],
            'starts_at' => '08:00', 'ends_at' => '09:00', 'grace_minutes' => 0, 'is_active' => true,
        ]);

        $this->getJson(route('schedules.rooms.suggest', ['q' => 'COM']))
            ->assertOk()
            ->assertJsonFragment(['COMLAB E2-207'])
            ->assertJsonFragment(['COMLAB-2']);
    }

    public function test_attendance_is_rejected_from_the_wrong_room_session(): void
    {
        [$terminalInRoom, $terminalElsewhere, $person] = $this->roomFixtures();

        $wrong = app(AttendanceService::class)->recordRecognition($terminalElsewhere, [
            'event_id' => (string) Str::uuid(),
            'result' => 'matched',
            'institution_id' => $person->institution_id,
            'confidence' => 0.95,
            'direction' => 'auto',
            'captured_at' => now()->setTime(13, 5)->toIso8601String(),
        ]);

        $this->assertSame('wrong_location', $wrong['action']);
        $this->assertNull($wrong['attendance']);
        $this->assertStringContainsString('COMLAB-2', $wrong['event']->failure_reason);
        $this->assertDatabaseCount('attendance_records', 0);

        $right = app(AttendanceService::class)->recordRecognition($terminalInRoom, [
            'event_id' => (string) Str::uuid(),
            'result' => 'matched',
            'institution_id' => $person->institution_id,
            'confidence' => 0.95,
            'direction' => 'auto',
            'captured_at' => now()->setTime(13, 6)->toIso8601String(),
        ]);

        $this->assertSame('time_in', $right['action']);
        $this->assertNotNull($right['attendance']);
        $this->assertSame('COMLAB-2', $right['attendance']->schedule->room_code);
    }

    public function test_client_supplied_room_values_are_ignored(): void
    {
        [$terminalInRoom, , $person] = $this->roomFixtures();

        // Browser/Python clients cannot select a room. Laravel resolves the
        // matching program-and-time schedule and saves its room instead.
        $this->withHeader('X-AttendPro-Service-Key', config('attendpro.python_service.key'))->postJson('/api/v1/recognition/events', [
            'event_id' => (string) Str::uuid(),
            'result' => 'matched',
            'institution_id' => $person->institution_id,
            'confidence' => 0.95,
            'direction' => 'auto',
            'captured_at' => now()->setTime(13, 5)->toIso8601String(),
            'metadata' => ['room' => 'COMLAB-2', 'location' => 'COMLAB-2'],
        ])->assertCreated()->assertJsonPath('data.action', 'time_in');

        $this->assertDatabaseHas('attendance_records', [
            'person_id' => $person->id,
            'schedule_id' => Schedule::query()->where('code', 'IT312')->value('id'),
            'location_id' => $terminalInRoom->location_id,
            'terminal_id' => null,
        ]);
        $this->assertSame($terminalInRoom->location_id, Schedule::query()->where('code', 'IT312')->firstOrFail()->location_id);
    }

    public function test_any_location_schedule_still_records_from_any_terminal(): void
    {
        $roomLocation = Location::query()->create(['code' => 'COMLAB-2', 'name' => 'Computer Laboratory - COMLAB-2']);
        $otherLocation = Location::query()->create(['code' => 'GC-101', 'name' => 'Global Classroom - GC-101']);
        $terminal = Terminal::query()->create([
            'uuid' => (string) Str::uuid(), 'code' => 'GC-01', 'name' => 'GC Camera',
            'location_id' => $otherLocation->id, 'status' => 'offline', 'is_active' => true,
        ]);
        $person = Person::query()->create([
            'institution_id' => '2026-00099', 'type' => 'student', 'first_name' => 'Open',
            'last_name' => 'Student', 'status' => 'active',
        ]);
        Schedule::query()->create([
            'code' => 'OPEN', 'name' => 'Open Schedule', 'person_type' => 'student',
            'location_type' => 'any', 'location_id' => null, 'room_code' => null,
            'days_of_week' => [now()->dayOfWeekIso], 'starts_at' => '00:00', 'ends_at' => '23:59',
            'check_in_opens_at' => '00:00', 'check_in_closes_at' => '23:59',
            'grace_minutes' => 10, 'checkout_required' => true, 'is_active' => true,
        ]);

        $result = app(AttendanceService::class)->recordRecognition($terminal, [
            'event_id' => (string) Str::uuid(),
            'result' => 'matched',
            'institution_id' => $person->institution_id,
            'confidence' => 0.95,
            'direction' => 'auto',
            'captured_at' => now()->toIso8601String(),
        ]);

        $this->assertSame('time_in', $result['action']);
        $this->assertNotNull($result['attendance']);
    }

    public function test_program_schedule_only_matches_students_in_that_program(): void
    {
        $person = Person::query()->create([
            'institution_id' => '2026-00101', 'type' => 'student', 'first_name' => 'Program',
            'last_name' => 'Student', 'program' => Person::PROGRAMS[1], 'status' => 'active',
        ]);

        Schedule::query()->create([
            'code' => 'BSBA-ONLY', 'name' => 'BSBA Schedule', 'person_type' => 'student',
            'program' => Person::PROGRAMS[0], 'location_type' => 'any', 'days_of_week' => [now()->dayOfWeekIso],
            'starts_at' => '00:00', 'ends_at' => '23:59', 'grace_minutes' => 10,
            'checkout_required' => true, 'is_active' => true,
        ]);

        $resolver = app(ScheduleResolver::class);
        $this->assertNull($resolver->resolve($person, now()));

        $matching = Schedule::query()->create([
            'code' => 'BSIT-ONLY', 'name' => 'BSIT Schedule', 'person_type' => 'student',
            'program' => Person::PROGRAMS[1], 'location_type' => 'any', 'days_of_week' => [now()->dayOfWeekIso],
            'starts_at' => '00:00', 'ends_at' => '23:59', 'grace_minutes' => 10,
            'checkout_required' => true, 'is_active' => true,
        ]);

        $this->assertTrue($resolver->resolve($person, now())?->is($matching));
    }

    /**
     * @return array{Terminal, Terminal, Person}
     */
    private function roomFixtures(): array
    {
        $department = Department::query()->create(['code' => 'IT', 'name' => 'Information Technology']);
        $roomLocation = Location::query()->create(['code' => 'COMLAB-2', 'name' => 'Computer Laboratory - COMLAB-2']);
        $otherLocation = Location::query()->create(['code' => 'GC-101', 'name' => 'Global Classroom - GC-101']);

        $terminalInRoom = Terminal::query()->create([
            'uuid' => (string) Str::uuid(), 'code' => 'COMLAB-2-CAM', 'name' => 'Comlab Camera',
            'location_id' => $roomLocation->id, 'status' => 'offline', 'is_active' => true,
        ]);
        $terminalElsewhere = Terminal::query()->create([
            'uuid' => (string) Str::uuid(), 'code' => 'GC-101-CAM', 'name' => 'GC Camera',
            'location_id' => $otherLocation->id, 'status' => 'offline', 'is_active' => true,
        ]);

        $person = Person::query()->create([
            'institution_id' => '2026-00312', 'type' => 'student', 'first_name' => 'Maria',
            'last_name' => 'Santos', 'department_id' => $department->id, 'status' => 'active',
        ]);

        $schedule = Schedule::query()->create([
            'code' => 'IT312', 'name' => 'IT 312', 'person_type' => 'student',
            'department_id' => $department->id, 'location_id' => $roomLocation->id,
            'location_type' => 'computer_laboratory', 'room_code' => 'COMLAB-2',
            'days_of_week' => [now()->dayOfWeekIso], 'starts_at' => '13:00', 'ends_at' => '15:00',
            'check_in_opens_at' => '12:00', 'check_in_closes_at' => '15:00',
            'grace_minutes' => 10, 'checkout_required' => true, 'is_active' => true,
        ]);
        $schedule->people()->sync([$person->id]);

        return [$terminalInRoom, $terminalElsewhere, $person];
    }
}
