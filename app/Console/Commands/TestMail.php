<?php

namespace App\Console\Commands;

use App\Mail\EmailSettingsTest;
use App\Services\MailConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMail extends Command
{
    protected $signature = 'attendpro:test-mail {recipient : Email address that should receive the test message}';

    protected $description = 'Send a safe test email using the configured Gmail SMTP sender.';

    public function handle(MailConfiguration $mail): int
    {
        $recipient = (string) $this->argument('recipient');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid recipient email address.');

            return self::FAILURE;
        }

        if ($missing = $mail->smtpMissing()) {
            $this->error('Gmail SMTP is not configured. Missing: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        try {
            Mail::to($recipient)->send(new EmailSettingsTest);
        } catch (Throwable) {
            $this->error('The test email could not be sent. Check Gmail SMTP settings and the App Password.');

            return self::FAILURE;
        }

        $this->info('Test email sent. Check the inbox and spam folder for '.$recipient.'.');

        return self::SUCCESS;
    }
}
