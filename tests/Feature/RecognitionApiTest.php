<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FacialProfile;
use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecognitionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_python_api_requires_the_shared_service_key(): void
    {
        $this->getJson('/api/v1/faces')->assertUnauthorized();
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
        $this->withHeader('X-AttendPro-Service-Key', config('attendpro.python_service.key'))
            ->getJson('/api/v1/faces')->assertOk();
    }

    public function test_python_can_read_laravel_recognition_configuration(): void
    {
        $this->withHeader('X-AttendPro-Service-Key', config('attendpro.python_service.key'))
            ->getJson('/api/v1/recognition/configuration')
            ->assertOk()
            ->assertJsonStructure(['data' => ['minimum_confidence', 'minimum_margin']]);
    }

    public function test_python_can_sync_faces_and_laravel_records_idempotent_attendance(): void
    {
        [, , $person] = $this->fixtures();

        FacialProfile::query()->create([
            'person_id' => $person->id,
            'embedding' => array_fill(0, 128, 0.125),
            'embedding_checksum' => hash('sha256', 'fixture'),
            'model' => 'test-model',
            'dimensions' => 128,
            'version' => 1,
            'is_active' => true,
            'consented_at' => now(),
        ]);

        $headers = ['X-AttendPro-Service-Key' => config('attendpro.python_service.key')];
        $this->withHeaders($headers)->getJson('/api/v1/faces')
            ->assertOk()
            ->assertJsonPath('data.0.institution_id', $person->institution_id)
            ->assertJsonCount(128, 'data.0.embedding');

        $payload = [
            'event_id' => (string) Str::uuid(),
            'result' => 'matched',
            'institution_id' => $person->institution_id,
            'confidence' => 0.95,
            'direction' => 'auto',
            'captured_at' => now()->setTime(8, 2)->toIso8601String(),
        ];

        $this->withHeaders($headers)->postJson('/api/v1/recognition/events', $payload)
            ->assertCreated()
            ->assertJsonPath('data.action', 'time_in')
            ->assertJsonPath('data.attendance.status', 'present')
            ->assertJsonPath('data.person.full_name', $person->full_name)
            ->assertJsonPath('data.person.type', 'student')
            ->assertJsonPath('data.person.department.name', 'Information Technology')
            ->assertJsonPath('data.person.program', 'BSIT')
            ->assertJsonPath('data.person.year_level', '4')
            ->assertJsonPath('data.person.email', 'student@hccd.edu.ph')
            ->assertJsonPath('data.person.phone', '09123456789');
        $this->withHeaders($headers)->postJson('/api/v1/recognition/events', $payload)
            ->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('recognition_events', ['terminal_id' => null, 'external_event_id' => $payload['event_id']]);
    }

    public function test_unknown_scan_is_queued_without_requiring_a_person(): void
    {
        $this->fixtures();

        $this->withHeader('X-AttendPro-Service-Key', config('attendpro.python_service.key'))->postJson('/api/v1/recognition/events', [
            'event_id' => (string) Str::uuid(),
            'result' => 'unknown',
            'confidence' => 0.32,
            'captured_at' => now()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.result', 'unknown');

        $this->assertDatabaseHas('recognition_events', ['result' => 'unknown', 'review_status' => 'pending']);
    }

    public function test_shared_python_service_can_store_a_consented_embedding(): void
    {
        [, , $person] = $this->fixtures();
        $operator = User::factory()->create();
        $headers = ['X-AttendPro-Service-Key' => config('attendpro.python_service.key')];
        $payload = [
            'institution_id' => $person->institution_id,
            'embedding' => array_fill(0, 32, 0.25),
            'model' => 'test-model',
            'consented_at' => now()->toIso8601String(),
            'enrolled_by' => $operator->id,
        ];

        $this->withHeaders($headers)->postJson('/api/v1/faces/enroll', $payload)
            ->assertCreated()->assertJsonPath('data.institution_id', $person->institution_id);

        $this->assertDatabaseHas('facial_profiles', [
            'person_id' => $person->id,
            'model' => 'test-model',
            'is_active' => true,
            'enrolled_by' => $operator->id,
        ]);
    }

    private function fixtures(): array
    {
        $department = Department::query()->create(['code' => 'IT', 'name' => 'Information Technology']);
        $location = Location::query()->create(['code' => 'GATE', 'name' => 'Main Gate']);
        $terminal = Terminal::query()->create([
            'uuid' => (string) Str::uuid(), 'code' => 'GATE-01', 'name' => 'Gate Camera',
            'location_id' => $location->id, 'status' => 'offline', 'is_active' => true,
        ]);
        $person = Person::query()->create([
            'institution_id' => '2026-00001', 'type' => 'student', 'first_name' => 'Test',
            'last_name' => 'Student', 'department_id' => $department->id, 'status' => 'active',
            'program' => 'BSIT', 'year_level' => '4', 'email' => 'student@hccd.edu.ph',
            'phone' => '09123456789', 'joined_on' => '2026-06-01',
        ]);
        Schedule::query()->create([
            'code' => 'DAY', 'name' => 'Day Schedule', 'person_type' => 'student',
            'department_id' => $department->id, 'location_id' => $location->id,
            'days_of_week' => [now()->dayOfWeekIso], 'starts_at' => '08:00', 'ends_at' => '17:00',
            'grace_minutes' => 10, 'checkout_required' => true, 'is_active' => true,
        ]);

        return [$terminal, null, $person];
    }
}
