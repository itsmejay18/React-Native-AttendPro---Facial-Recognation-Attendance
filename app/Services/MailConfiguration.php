<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

class MailConfiguration
{
    /** @return array<int, string> */
    public function smtpMissing(): array
    {
        $missing = [];
        if (config('mail.default') !== 'smtp') { $missing[] = 'MAIL_MAILER'; }
        if (blank(config('mail.mailers.smtp.host'))) { $missing[] = 'MAIL_HOST'; }
        if (blank(config('mail.mailers.smtp.port'))) { $missing[] = 'MAIL_PORT'; }
        if (blank(config('mail.mailers.smtp.username'))) { $missing[] = 'MAIL_USERNAME'; }
        if (blank(config('mail.mailers.smtp.password'))) { $missing[] = 'MAIL_PASSWORD'; }
        if (blank(config('mail.from.address'))) { $missing[] = 'MAIL_FROM_ADDRESS'; }

        return $missing;
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        return [
            'enabled' => (bool) config('attendpro.notifications.attendance_email', true),
            'mailer' => (string) config('mail.default', 'smtp'),
            'host' => (string) config('mail.mailers.smtp.host', ''),
            'port' => (int) config('mail.mailers.smtp.port', 587),
            'username' => (string) config('mail.mailers.smtp.username', ''),
            'scheme' => (string) (config('mail.mailers.smtp.scheme') ?: 'tls'),
            'timeout' => (int) (config('mail.mailers.smtp.timeout') ?: 10),
            'from_address' => (string) config('mail.from.address', ''),
            'from_name' => (string) config('mail.from.name', config('app.name')),
        ];
    }

    /** @param array<string, mixed> $settings */
    public function save(array $settings): void
    {
        $environment = [
            'ATTENDPRO_ATTENDANCE_EMAIL_NOTIFICATIONS' => $settings['enabled'] ? 'true' : 'false',
            'MAIL_MAILER' => $settings['mailer'],
            'MAIL_SCHEME' => $settings['scheme'],
            'MAIL_ENCRYPTION' => $settings['scheme'] === 'smtps' ? 'ssl' : 'tls',
            'MAIL_REQUIRE_TLS' => $settings['scheme'] === 'smtp' ? 'true' : 'false',
            'MAIL_TIMEOUT' => (string) $settings['timeout'],
            'MAIL_HOST' => $settings['host'],
            'MAIL_PORT' => (string) $settings['port'],
            'MAIL_USERNAME' => $settings['username'],
            'MAIL_FROM_ADDRESS' => $settings['from_address'],
            'MAIL_FROM_NAME' => $settings['from_name'],
        ];

        if (filled($settings['password'] ?? null)) {
            $environment['MAIL_PASSWORD'] = $settings['password'];
        }

        $path = base_path('.env');
        $contents = File::exists($path) ? File::get($path) : '';
        foreach ($environment as $key => $value) {
            $line = $key.'='.$this->escape((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? (preg_replace($pattern, $line, $contents) ?? $contents)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($path, $contents);
        Artisan::call('config:clear');
        Mail::purge('smtp');
        Mail::purge('log');
        config([
            'attendpro.notifications.attendance_email' => $settings['enabled'],
            'mail.default' => $settings['mailer'],
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.scheme' => $settings['scheme'],
            'mail.mailers.smtp.require_tls' => $settings['scheme'] === 'smtp',
            'mail.mailers.smtp.timeout' => $settings['timeout'],
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'],
        ]);
    }

    private function escape(string $value): string
    {
        return preg_match('/[\s#"\\\\]/', $value)
            ? '"'.addcslashes($value, "\\\"").'"'
            : $value;
    }
}
