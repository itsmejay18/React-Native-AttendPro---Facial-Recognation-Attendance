<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Person;
use App\Models\Schedule;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicAttendanceKioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'attendpro.public_attendance.enabled' => true,
            'attendpro.public_attendance.allowed_ips' => ['127.0.0.1', '::1'],
            'attendpro.python_service.url' => 'http://127.0.0.1:5001',
            'attendpro.python_service.key' => 'test-service-key',
        ]);
    }

    public function test_landing_links_to_a_login_free_local_attendance_station(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Mark Attendance')
            ->assertSee(route('attendance.kiosk'), false)
            ->assertSee('data-public-attendance-status', false);

        $this->get(route('attendance.kiosk'))
            ->assertOk()
            ->assertSee('No login required')
            ->assertSee('data-recognition-scanner', false)
            ->assertSee(route('attendance.kiosk.scan'), false)
            ->assertDontSee('Start recognition server');
    }

    public function test_guest_can_check_safe_status_and_scan_from_localhost(): void
    {
        [, $person] = $this->localAttendanceFixtures();
        $eventId = (string) Str::uuid();

        Http::fake([
            'http://127.0.0.1:5001/v1/health' => Http::response(['data' => [
                'status' => 'ready',
                'configured' => true,
                'models_downloaded' => true,
                'engine_initialized' => true,
                'profiles_loaded' => 4,
                'internal_path' => 'must-not-leak',
            ]]),
            'http://127.0.0.1:5001/v1/recognize' => Http::response([
                'message' => 'Camera frame processed.',
                'data' => [
                    'result' => 'matched',
                    'institution_id' => $person->institution_id,
                    'confidence' => 0.95,
                    'direction' => 'auto',
                    'captured_at' => now()->toIso8601String(),
                    'metadata' => ['recognition_model' => 'test-model'],
                    'recognition' => [
                        'event_id' => $eventId,
                        'confidence' => 0.95,
                        'candidate' => [
                            'institution_id' => $person->institution_id,
                            'full_name' => $person->full_name,
                            'person_type' => $person->type,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson(route('attendance.kiosk.status'))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.models_ready', true)
            ->assertJsonPath('data.profiles_loaded', 4)
            ->assertJsonMissingPath('data.internal_path');

        $this->postJson(route('attendance.kiosk.scan'), [
            'image' => $this->fakeJpeg(),
            'direction' => 'auto',
        ])->assertOk()
            ->assertJsonPath('data.action', 'time_in')
            ->assertJsonPath('data.person.full_name', $person->full_name);

        $this->assertDatabaseHas('recognition_events', [
            'terminal_id' => null,
            'person_id' => $person->id,
            'result' => 'matched',
        ]);
    }

    public function test_public_attendance_is_restricted_to_the_same_device_and_cannot_start_python(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->getJson(route('attendance.kiosk.status'))
            ->assertForbidden();

        $this->postJson(route('recognition.python.start'))->assertUnauthorized();
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
        Schedule::query()->create([
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

        return [$terminal, $person];
    }
}
