<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SystemApiConfiguration
{
    /** @return array<string, mixed> */
    public function current(): array
    {
        return [
            'google_client_id' => (string) config('services.google.client_id', ''),
            'google_client_secret_configured' => filled(config('services.google.client_secret')),
            'google_redirect_uri' => (string) config('services.google.redirect', ''),
            'python_url' => (string) config('attendpro.python_service.url', ''),
            'python_timeout' => (int) config('attendpro.python_service.timeout', 30),
            'laravel_api_url' => (string) env('ATTENDPRO_LARAVEL_API_URL', url('/api/v1')),
            'cors_allowed_origins' => implode(', ', (array) config('cors.allowed_origins', [])),
            'public_attendance_enabled' => (bool) config('attendpro.public_attendance.enabled', true),
            'public_attendance_allowed_ips' => implode(', ', (array) config('attendpro.public_attendance.allowed_ips', [])),
        ];
    }

    /** @param array<string, mixed> $settings */
    public function save(array $settings): void
    {
        $environment = [
            'GOOGLE_CLIENT_ID' => $settings['google_client_id'] ?? '',
            'GOOGLE_REDIRECT_URI' => $settings['google_redirect_uri'] ?? '',
            'ATTENDPRO_PYTHON_URL' => $settings['python_url'],
            'ATTENDPRO_PYTHON_TIMEOUT' => (string) $settings['python_timeout'],
            'ATTENDPRO_LARAVEL_API_URL' => $settings['laravel_api_url'],
            'ATTENDPRO_CORS_ALLOWED_ORIGINS' => $this->csv($settings['cors_allowed_origins'] ?? ''),
            'ATTENDPRO_PUBLIC_ATTENDANCE_ENABLED' => $settings['public_attendance_enabled'] ? 'true' : 'false',
            'ATTENDPRO_PUBLIC_ATTENDANCE_ALLOWED_IPS' => $this->csv($settings['public_attendance_allowed_ips'] ?? ''),
        ];

        if (filled($settings['google_client_secret'] ?? null)) {
            $environment['GOOGLE_CLIENT_SECRET'] = $settings['google_client_secret'];
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
    }

    private function csv(string $value): string
    {
        return implode(',', array_values(array_filter(array_map('trim', explode(',', $value)))));
    }

    private function escape(string $value): string
    {
        return preg_match('/[\s#"\\\\]/', $value)
            ? '"'.addcslashes($value, "\\\"").'"'
            : $value;
    }
}
