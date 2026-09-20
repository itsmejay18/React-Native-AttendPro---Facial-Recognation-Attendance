<?php

namespace App\Mail;

use App\Models\AttendanceRecord;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class AttendanceRecorded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Person $person,
        public readonly AttendanceRecord $record,
        public readonly string $action,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'AttendPro attendance confirmation');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.attendance-recorded');
    }
}
