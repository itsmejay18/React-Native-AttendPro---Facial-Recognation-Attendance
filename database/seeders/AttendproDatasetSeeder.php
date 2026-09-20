<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Location;
use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Models\Schedule;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Restores the portable AttendPro demo dataset shipped in this repository
 * (database/seeders/data/attendpro-dataset/, committed to GitHub) so a fresh
 * clone gets the same students, staff accounts, schedules, terminal, sample
 * face images, and attendance history.
 *
 * What it does NOT restore, on purpose:
 * - password hashes, terminal API tokens, session/cache/audit rows
 *   (secrets and ephemeral data are never committed);
 * - facial embedding vectors: the stored rows were encrypted with a previous
 *   APP_KEY and cannot be decrypted. The enrollment IMAGES are restored to
 *   storage instead; run `php artisan attendpro:faces:re-extract` (Python
 *   service must be running) to rebuild face profiles from those images
 *   under the active recognition backend.
 *
 * The seeder is idempotent: every row is matched by a natural key
 * (institution ID, email, UUID, code) and skipped when already present.
 */
class AttendproDatasetSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DATA_DIR = __DIR__.'/data/attendpro-dataset';

    public function run(): void
    {
        if (! is_dir(self::DATA_DIR)) {
            $this->command->warn('AttendproDatasetSeeder: dataset directory is missing, nothing to seed.');

            return;
        }

        $this->seedPeople();
        $images = $this->restoreEnrollmentImages();
        $this->seedUsers();
        $this->seedSchedules();
        $this->seedTerminals();
        $this->seedAttendance();
        $this->seedRecognitionEvents();

        $this->command->info("AttendPro dataset restored ({$images} enrollment images in storage).");
        $this->command->line('Next: start the Python service, then run php artisan attendpro:faces:re-extract');
        $this->command->line('to rebuild facial profiles from the restored images.');
    }

    private function read(string $file): array
    {
        $path = self::DATA_DIR.'/'.$file;
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function keepTimestamps(string $table, string $column, mixed $value, ?string $createdAt, ?string $updatedAt): void
    {
        if ($createdAt === null && $updatedAt === null) {
            return;
        }

        DB::table($table)->where($column, $value)->update(array_filter([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]));
    }

    private function seedPeople(): void
    {
        foreach ($this->read('people.json') as $row) {
            $person = Person::withTrashed()->updateOrCreate(
                ['institution_id' => $row['institution_id']],
                [
                    'type' => $row['type'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'suffix' => $row['suffix'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'department_id' => $row['department_code']
                        ? Department::query()->where('code', $row['department_code'])->value('id')
                        : null,
                    'program' => $row['program'],
                    'year_level' => $row['year_level'],
                    'position' => $row['position'],
                    'status' => $row['status'],
                    'joined_on' => $row['joined_on'],
                    'metadata' => $row['metadata'],
                ]
            );

            if ($row['deleted_at']) {
                $person->trashed() || $person->delete();
            } elseif ($person->trashed()) {
                $person->restore();
            }

            $this->keepTimestamps('people', 'institution_id', $row['institution_id'], $row['created_at'], $row['updated_at']);
        }
    }

    /**
     * Copies the shipped enrollment images into the local disk layout
     * (storage/app/private/facial-enrollments/...). Returns the file count.
     */
    private function restoreEnrollmentImages(): int
    {
        $count = 0;
        foreach ($this->read('facial_enrollments.json') as $session) {
            foreach ($session['images'] as $image) {
                $relative = ltrim($image['path'], '/');
                if ($relative === '') {
                    continue;
                }
                $source = self::DATA_DIR.'/facial-enrollments/'.preg_replace('#^facial-enrollments/#', '', $relative);
                $target = storage_path('app/private/'.$relative);
                if (! is_file($source)) {
                    $this->command->warn("Missing dataset image: {$relative}");
                    continue;
                }
                if (! is_file($target)) {
                    @mkdir(dirname($target), 0777, true);
                    copy($source, $target);
                }
                $count++;
            }
        }

        return $count;
    }

    private function seedUsers(): void
    {
        $password = (string) (getenv('ATTENDPRO_DEMO_STUDENT_PASSWORD') ?: 'AttendPro-Student-2026');

        foreach ($this->read('users.json') as $row) {
            $personId = $row['person_institution_id']
                ? Person::withTrashed()->where('institution_id', $row['person_institution_id'])->value('id')
                : null;
            if ($row['person_institution_id'] && ! $personId) {
                $this->command->warn("Skipping user {$row['email']}: person {$row['person_institution_id']} not found.");
                continue;
            }

            $user = User::query()->where('email', $row['email'])->first();
            if (! $user) {
                $user = User::query()->create([
                    'person_id' => $personId,
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $password,
                    'is_active' => $row['is_active'],
                ]);
            } else {
                $user->fill([
                    'person_id' => $personId,
                    'name' => $row['name'],
                    'is_active' => $row['is_active'],
                ])->save();
            }

            foreach ((array) ($row['roles'] ?? []) as $role) {
                Role::findOrCreate($role, 'web');
            }
            if (! empty($row['roles'])) {
                $user->syncRoles($row['roles']);
            }
        }

        $this->command->line('Seeded campus accounts use the demo password (change after first login).');
    }

    private function seedSchedules(): void
    {
        $hasLocationType = Schema::hasColumn('schedules', 'location_type');
        $hasRoomCode = Schema::hasColumn('schedules', 'room_code');

        foreach ($this->read('schedules.json') as $row) {
            $days = $row['days_of_week'];
            if (is_string($days)) {
                $days = json_decode($days, true) ?? [];
            }

            $attributes = [
                'name' => $row['name'],
                'person_type' => $row['person_type'],
                'department_id' => $row['department_code']
                    ? Department::query()->where('code', $row['department_code'])->value('id')
                    : null,
                'location_id' => $row['location_code']
                    ? Location::query()->where('code', $row['location_code'])->value('id')
                    : null,
                'days_of_week' => $days,
                'starts_at' => $row['starts_at'],
                'ends_at' => $row['ends_at'],
                'check_in_opens_at' => $row['check_in_opens_at'],
                'check_in_closes_at' => $row['check_in_closes_at'],
                'grace_minutes' => $row['grace_minutes'],
                'checkout_required' => (bool) $row['checkout_required'],
                'effective_from' => $row['effective_from'],
                'effective_until' => $row['effective_until'],
                'is_active' => (bool) $row['is_active'],
            ];
            if ($hasLocationType) {
                $attributes['location_type'] = $row['location_type'] ?? 'any';
            }
            if ($hasRoomCode) {
                $attributes['room_code'] = $row['room_code'] ?? null;
            }

            Schedule::query()->updateOrCreate(['code' => $row['code']], $attributes);
        }
    }

    private function seedTerminals(): void
    {
        foreach ($this->read('terminals.json') as $row) {
            $locationId = $row['location_code']
                ? Location::query()->where('code', $row['location_code'])->value('id')
                : Location::query()->value('id');
            if (! $locationId) {
                $this->command->warn("Skipping terminal {$row['code']}: no location available.");
                continue;
            }

            // Never overwrite an existing UUID: it is unique and may already be
            // referenced elsewhere. Tokens are re-issued by recognition-setup.
            $terminal = Terminal::query()->where('code', $row['code'])->first();
            if (! $terminal) {
                Terminal::query()->create([
                    'uuid' => $row['uuid'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'location_id' => $locationId,
                    'status' => 'offline',
                    'is_active' => $row['is_active'],
                    'capabilities' => $row['capabilities'],
                ]);
            } else {
                $terminal->fill([
                    'name' => $row['name'],
                    'location_id' => $locationId,
                    'is_active' => $row['is_active'],
                    'capabilities' => $row['capabilities'],
                ])->save();
            }
        }
    }

    private function seedAttendance(): void
    {
        foreach ($this->read('attendance_records.json') as $row) {
            $personId = Person::withTrashed()->where('institution_id', $row['person_institution_id'])->value('id');
            if (! $personId) {
                $this->command->warn("Skipping attendance {$row['uuid']}: person not found.");
                continue;
            }

            AttendanceRecord::query()->firstOrCreate(['uuid' => $row['uuid']], [
                'person_id' => $personId,
                'schedule_id' => $row['schedule_code']
                    ? Schedule::query()->where('code', $row['schedule_code'])->value('id')
                    : null,
                'location_id' => $row['location_code']
                    ? Location::query()->where('code', $row['location_code'])->value('id')
                    : null,
                'terminal_id' => $row['terminal_code']
                    ? Terminal::query()->where('code', $row['terminal_code'])->value('id')
                    : null,
                'attendance_date' => $row['attendance_date'],
                'time_in' => $row['time_in'],
                'time_out' => $row['time_out'],
                'status' => $row['status'],
                'source' => $row['source'],
                'match_confidence' => $row['match_confidence'],
                'notes' => $row['notes'],
                'recorded_by' => $row['recorded_by_email']
                    ? User::query()->where('email', $row['recorded_by_email'])->value('id')
                    : null,
                'corrected_by' => $row['corrected_by_email']
                    ? User::query()->where('email', $row['corrected_by_email'])->value('id')
                    : null,
                'corrected_at' => $row['corrected_at'],
                'revision' => $row['revision'] ?? 1,
            ]);

            $this->keepTimestamps('attendance_records', 'uuid', $row['uuid'], $row['created_at'], $row['updated_at']);
        }
    }

    private function seedRecognitionEvents(): void
    {
        foreach ($this->read('recognition_events.json') as $row) {
            $terminalId = $row['terminal_code']
                ? Terminal::query()->where('code', $row['terminal_code'])->value('id')
                : null;
            if (! $terminalId) {
                $this->command->warn("Skipping recognition event {$row['uuid']}: terminal not found.");
                continue;
            }

            RecognitionEvent::query()->firstOrCreate(['uuid' => $row['uuid']], [
                'external_event_id' => $row['external_event_id'],
                'terminal_id' => $terminalId,
                'person_id' => $row['person_institution_id']
                    ? Person::withTrashed()->where('institution_id', $row['person_institution_id'])->value('id')
                    : null,
                'attendance_record_id' => $row['attendance_uuid']
                    ? AttendanceRecord::query()->where('uuid', $row['attendance_uuid'])->value('id')
                    : null,
                'result' => $row['result'],
                'confidence' => $row['confidence'],
                'direction' => $row['direction'] ?? 'auto',
                'captured_at' => $row['captured_at'],
                'review_status' => $row['review_status'] ?? 'pending',
                'failure_reason' => $row['failure_reason'],
                'metadata' => $row['metadata'],
                'reviewed_by' => $row['reviewed_by_email']
                    ? User::query()->where('email', $row['reviewed_by_email'])->value('id')
                    : null,
                'reviewed_at' => $row['reviewed_at'],
            ]);

            $this->keepTimestamps('recognition_events', 'uuid', $row['uuid'], $row['created_at'], $row['updated_at']);
        }
    }
}
