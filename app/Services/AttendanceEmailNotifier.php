<?php

namespace App\Services;

use App\Mail\AttendanceRecorded;
use App\Models\AttendanceRecord;
use App\Models\Person;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AttendanceEmailNotifier
{
    public function __construct(private readonly MailConfiguration $mail) {}

    public function send(Person $person, AttendanceRecord $record, string $action): void
    {
        if (! config('attendpro.notifications.attendance_email', true)
            || blank($person->email)
            || ! in_array($action, ['time_in', 'time_out'], true)) {
            return;
        }

        if ($missing = $this->mail->smtpMissing()) {
            Log::warning('Gmail SMTP is not configured for attendance email notifications.', [
                'missing' => $missing,
            ]);

            return;
        }

        try {
            $record->loadMissing(['location', 'schedule']);
            $message = new AttendanceRecorded($person, $record, $action);

            if (config('queue.default') !== 'sync') {
                Mail::to($person->email)->queue($message);
            } else {
                Mail::to($person->email)->send($message);
            }
        } catch (Throwable $exception) {
            Log::warning('Attendance confirmation email could not be sent.', [
                'person_id' => $person->id,
                'attendance_record_id' => $record->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
