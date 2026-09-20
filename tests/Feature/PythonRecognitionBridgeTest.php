<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use App\Models\Terminal;
use App\Models\User;
use App\Services\PythonServiceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PythonRecognitionBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'attendpro.python_service.url' => 'http://127.0.0.1:5001',
            'attendpro.python_service.key' => 'test-service-key',
            'attendpro.recognition.enrollment_samples' => 1,
            'attendpro.recognition.retain_enrollment_images' => false,
        ]);
    }

    public function test_admin_can_check_python_service_status(): void
    {
        Http::fake(['http://127.0.0.1:5001/v1/health' => Http::response([
            'data' => ['status' => 'ready', 'configured' => true, 'profiles_loaded' => 2],
        ])]);

        $this->actingAs(User::factory()->create())
            ->getJson('/recognition/python/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');

        Http::assertSent(fn ($request) => $request->hasHeader('X-AttendPro-Service-Key', 'test-service-key'));
    }

    public function test_admin_can_request_transient_insightface_landmarks_for_camera_guidance(): void
    {
        Http::fake(['http://127.0.0.1:5001/v1/preview' => Http::response([
            'data' => [
                'supported' => true,
                'backend' => 'insightface',
                'image_width' => 640,
                'image_height' => 480,
                'face_count' => 1,
                'faces' => [[
                    'detection_score' => 0.98,
                    'keypoints' => [['x' => 100, 'y' => 110]],
                ]],
            ],
        ])]);

        $this->actingAs(User::factory()->create())
            ->post('/recognition/python/preview', ['image' => $this->fakeJpeg()])
            ->assertOk()
            ->assertJsonPath('data.face_count', 1)
            ->assertJsonPath('data.faces.0.keypoints.0.x', 100);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:5001/v1/preview'
            && $request->isMultipart()
            && $request->hasHeader('X-AttendPro-Service-Key', 'test-service-key'));
    }

    public function test_attendance_staff_can_start_the_local_python_server_from_laravel(): void
    {
        config(['attendpro.python_service.managed' => true]);
        $manager = Mockery::mock(PythonServiceManager::class);
        $manager->shouldReceive('start')->once()->andReturn([
            'status' => 'ready',
            'configured' => true,
            'models_downloaded' => true,
            'profiles_loaded' => 0,
        ]);
        $this->app->instance(PythonServiceManager::class, $manager);

        $this->actingAs(User::factory()->create())
            ->postJson('/recognition/python/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_python_server_start_is_restricted_to_local_admin_or_staff_requests(): void
    {
        config(['attendpro.python_service.managed' => true]);

        $this->actingAs(User::factory()->reviewer()->create())
            ->postJson('/recognition/python/start')
            ->assertForbidden();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->actingAs(User::factory()->create())
            ->postJson('/recognition/python/start')
            ->assertForbidden();
    }

    public function test_camera_frame_is_forwarded_to_python_without_using_a_terminal(): void
    {
        [, $person, $schedule] = $this->localAttendanceFixtures();
        Http::fake(['http://127.0.0.1:5001/v1/recognize' => Http::response([
            'message' => 'Camera frame processed.',
            'data' => [
                'event_id' => (string) Str::uuid(),
                'result' => 'matched',
                'institution_id' => $person->institution_id,
                'confidence' => 0.95,
                'direction' => 'auto',
                'captured_at' => now()->toIso8601String(),
                'metadata' => ['recognition_model' => 'test-model'],
                'recognition' => [
                    'event_id' => (string) Str::uuid(),
                    'confidence' => 0.95,
                    'candidate' => [
                        'institution_id' => $person->institution_id,
                        'full_name' => $person->full_name,
                        'person_type' => $person->type,
                    ],
                ],
            ],
        ])]);

        $response = $this->actingAs(User::factory()->create())->postJson('/recognition/python/scan', [
            'image' => $this->fakeJpeg(),
            'direction' => 'auto',
            'schedule_id' => $schedule->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.action', 'time_in')
            ->assertJsonPath('data.person.full_name', $person->full_name);
        Http::assertSent(fn ($request) => $request->isMultipart()
            && $request->hasHeader('X-AttendPro-Service-Key', 'test-service-key')
            && ! $request->hasHeader('Authorization'));
        $this->assertDatabaseHas('recognition_events', [
            'terminal_id' => null,
            'person_id' => $person->id,
            'result' => 'matched',
        ]);
    }

    public function test_enrollment_requires_documented_consent(): void
    {
        $person = Person::query()->create([
            'institution_id' => '2026-00001', 'type' => 'student',
            'first_name' => 'Test', 'last_name' => 'Student', 'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create())->postJson('/recognition/python/enroll', [
            'image' => $this->fakeJpeg(),
            'institution_id' => $person->institution_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('consent');
    }

    public function test_enrollment_forwards_the_authorized_staff_user_to_python(): void
    {
        Http::fake([
            'http://127.0.0.1:5001/v1/extract' => Http::response([
                'message' => 'Face embedding extracted.',
                'data' => ['embedding' => array_fill(0, 128, 0.1), 'model' => 'test-model', 'dimensions' => 128],
            ]),
            'http://127.0.0.1:5001/v1/index-profiles' => Http::response([
                'message' => 'Facial profiles added to local index.',
                'data' => ['profiles' => 1],
            ]),
        ]);

        $staff = User::factory()->create();
        $person = Person::query()->create([
            'institution_id' => 'STAFF-001', 'type' => 'staff',
            'first_name' => 'Attendance', 'last_name' => 'Staff', 'status' => 'active',
        ]);

        $this->actingAs($staff)->postJson('/recognition/python/enroll', [
            'image' => $this->fakeJpeg(),
            'institution_id' => $person->institution_id,
            'consent' => '1',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request->isMultipart()
            && $request->url() === 'http://127.0.0.1:5001/v1/extract');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:5001/v1/index-profiles'
            && $request[0]['institution_id'] === $person->institution_id
            && $request[0]['person_id'] === $person->id);
        $this->assertDatabaseHas('facial_profiles', [
            'person_id' => $person->id,
            'model' => 'test-model',
            'dimensions' => 128,
            'is_active' => true,
            'enrolled_by' => $staff->id,
        ]);
    }

    public function test_successful_face_enrollment_activates_the_linked_campus_account(): void
    {
        Http::fake([
            'http://127.0.0.1:5001/v1/extract' => Http::response([
                'message' => 'Face embedding extracted.',
                'data' => ['embedding' => array_fill(0, 128, 0.1), 'model' => 'test-model', 'dimensions' => 128],
            ]),
            'http://127.0.0.1:5001/v1/index-profiles' => Http::response([
                'message' => 'Facial profiles added to local index.',
                'data' => ['profiles' => 1],
            ]),
        ]);

        $operator = User::factory()->create();
        $person = Person::query()->create([
            'institution_id' => 'STUDENT-ACTIVATE-001',
            'type' => 'student',
            'first_name' => 'Ready',
            'last_name' => 'Student',
            'email' => 'ready.student@example.test',
            'status' => 'active',
        ]);
        $account = User::query()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $person->email,
            'password' => 'StrongPass!2026',
            'is_active' => false,
        ]);
        $this->actingAs($operator)->postJson('/recognition/python/enroll', [
            'image' => $this->fakeJpeg(),
            'institution_id' => $person->institution_id,
            'consent' => '1',
        ])->assertOk();

        $this->assertTrue($account->fresh()->is_active);
        $this->assertDatabaseHas('facial_profiles', [
            'person_id' => $person->id,
            'model' => 'test-model',
            'dimensions' => 128,
            'is_active' => true,
        ]);
    }

    private function fakeJpeg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'capture.jpg',
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true),
        );
    }

    private function localAttendanceFixtures(): array
    {
        $location = Location::query()->create(['code' => 'LOCAL', 'name' => 'Local Station']);
        $terminal = Terminal::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'LOCALHOST-01',
            'name' => 'Local Laravel Camera',
            'location_id' => $location->id,
            'status' => 'online',
            'is_active' => true,
            'capabilities' => ['recognition' => true, 'face_sync' => true, 'enrollment' => true],
        ]);
        $person = Person::query()->create([
            'institution_id' => '2026-00001',
            'type' => 'student',
            'first_name' => 'Roger',
            'last_name' => 'Ababon',
            'status' => 'active',
        ]);
        $schedule = Schedule::query()->create([
            'code' => 'TODAY',
            'name' => 'Today',
            'person_type' => 'student',
            'location_id' => $location->id,
            'days_of_week' => [now()->dayOfWeekIso],
            'starts_at' => '00:00',
            'ends_at' => '23:59',
            'check_in_opens_at' => '00:00',
            'check_in_closes_at' => '23:59',
            'is_active' => true,
        ]);

        return [$terminal, $person, $schedule];
    }
}
