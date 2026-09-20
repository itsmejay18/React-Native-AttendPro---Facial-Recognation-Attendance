<?php

namespace App\Console\Commands;

use App\Services\MailConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckConfiguration extends Command
{
    protected $signature = 'attendpro:check-config';

    protected $description = 'Check local AttendPro configuration without displaying sensitive values.';

    public function handle(MailConfiguration $mail): int
    {
        $failed = false;
        $appKeyConfigured = filled(config('app.key'));
        $failed = ! $appKeyConfigured;
        $this->line('Laravel APP_KEY: '.($appKeyConfigured ? 'OK' : 'MISSING APP_KEY'));

        try {
            DB::connection()->getPdo();
            $this->line('Database: OK');
        } catch (Throwable) {
            $failed = true;
            $this->line('Database: UNAVAILABLE');
        }

        if ($missing = $mail->smtpMissing()) {
            $failed = true;
            $this->line('Gmail SMTP: MISSING '.implode(', ', $missing));
        } else {
            $this->line('Gmail SMTP: CONFIGURED');
        }

        $googleMissing = [];
        foreach (['client_id' => 'GOOGLE_CLIENT_ID', 'client_secret' => 'GOOGLE_CLIENT_SECRET', 'redirect' => 'GOOGLE_REDIRECT_URI'] as $configKey => $name) {
            if (blank(config("services.google.{$configKey}"))) { $googleMissing[] = $name; }
        }
        if ($googleMissing) {
            $failed = true;
            $this->line('Google OAuth: MISSING '.implode(', ', $googleMissing));
        } else {
            $this->line('Google OAuth: CONFIGURED');
        }

        $this->line('Face Service URL: '.(filled(config('attendpro.python_service.url')) ? 'CONFIGURED' : 'MISSING ATTENDPRO_PYTHON_URL'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
