<?php

namespace Tests\Feature;

use App\Mail\AttendanceRecorded;
use App\Models\AttendanceRecord;
use App\Models\Person;
use App\Services\AttendanceEmailNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AttendanceEmailNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'sender@example.test',
            'mail.mailers.smtp.password' => 'test-app-password',
            'mail.from.address' => 'sender@example.test',
        ]);
    }

    public function test_it_sends_a_confirmation_for_a_new_attendance_time_in(): void
    {
        config(['attendpro.notifications.attendance_email' => true]);
        Mail::fake();

        $person = Person::query()->create([
            'institution_id' => 'MAIL-2026-001',
            'type' => 'student',
            'first_name' => 'Email',
            'last_name' => 'Recipient',
            'email' => 'recipient@example.test',
            'status' => 'active',
            'joined_on' => today(),
        ]);
        $record = AttendanceRecord::query()->create([
            'uuid' => (string) str()->uuid(),
            'person_id' => $person->id,
            'attendance_date' => today(),
            'time_in' => now(),
            'status' => 'present',
            'source' => 'recognition',
        ]);

        app(AttendanceEmailNotifier::class)->send($person, $record, 'time_in');

        Mail::assertQueued(AttendanceRecorded::class, function (AttendanceRecorded $mail) use ($person): bool {
            return $mail->hasTo($person->email) && $mail->action === 'time_in';
        });
    }

    public function test_it_does_not_email_duplicate_or_incomplete_scans(): void
    {
        config(['attendpro.notifications.attendance_email' => true]);
        Mail::fake();

        $person = Person::query()->create([
            'institution_id' => 'MAIL-2026-002',
            'type' => 'student',
            'first_name' => 'No',
            'last_name' => 'Duplicate',
            'email' => 'duplicate@example.test',
            'status' => 'active',
            'joined_on' => today(),
        ]);
        $record = AttendanceRecord::query()->create([
            'uuid' => (string) str()->uuid(),
            'person_id' => $person->id,
            'attendance_date' => today(),
            'time_in' => now(),
            'status' => 'present',
            'source' => 'recognition',
        ]);

        app(AttendanceEmailNotifier::class)->send($person, $record, 'already_timed_in');

        Mail::assertNothingSent();
    }
}
