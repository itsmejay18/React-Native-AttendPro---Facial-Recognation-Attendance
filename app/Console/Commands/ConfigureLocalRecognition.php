<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class ConfigureLocalRecognition extends Command
{
    protected $signature = 'attendpro:recognition-setup';

    protected $description = 'Configure Laravel and the Python recognition service for one-device localhost use';

    public function handle(): int
    {
        $environmentPath = base_path('.env');
        if (! is_file($environmentPath)) {
            throw new RuntimeException('The repository .env file is missing. Copy .env.example first.');
        }

        $contents = file_get_contents($environmentPath);
        if ($contents === false) {
            throw new RuntimeException('The repository .env file could not be read.');
        }

        $serviceKey = $this->environmentValue($contents, 'ATTENDPRO_PYTHON_SERVICE_KEY');
        if ($serviceKey === '') {
            $serviceKey = Str::random(64);
        }

        $settings = [
            'APP_URL' => 'http://127.0.0.1:8000',
            'ATTENDPRO_PYTHON_URL' => 'http://127.0.0.1:5001',
            'ATTENDPRO_PYTHON_SERVICE_KEY' => $serviceKey,
            'ATTENDPRO_PYTHON_MANAGED' => 'true',
            'ATTENDPRO_LARAVEL_API_URL' => 'http://127.0.0.1:8000/api/v1',
            'ATTENDPRO_PYTHON_HOST' => '127.0.0.1',
            'ATTENDPRO_PYTHON_PORT' => '5001',
        ];

        foreach ($settings as $key => $value) {
            $contents = $this->setEnvironmentValue($contents, $key, $value);
        }

        // Default face backend is InsightFace buffalo_l (SCRFD + ArcFace R100).
        // Only fill it in when missing so an explicit sface/dlib choice is kept.
        if ($this->environmentValue($contents, 'ATTENDPRO_FACE_BACKEND') === '') {
            $contents = $this->setEnvironmentValue($contents, 'ATTENDPRO_FACE_BACKEND', 'insightface');
        }
        if ($this->environmentValue($contents, 'ATTENDPRO_INSIGHTFACE_PACK') === '') {
            $contents = $this->setEnvironmentValue($contents, 'ATTENDPRO_INSIGHTFACE_PACK', 'buffalo_l');
        }

        if (file_put_contents($environmentPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The localhost recognition settings could not be written to .env.');
        }

        $this->callSilent('config:clear');

        $this->components->info('Local facial recognition is configured.');
        $this->line('Laravel: http://127.0.0.1:8000');
        $this->line('Python:  http://127.0.0.1:5001');
        $this->line('Laravel assigns attendance using each student\'s active program, schedule time, and room.');

        return self::SUCCESS;
    }

    private function environmentValue(string $contents, string $key): string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return '';
        }

        return trim(trim($matches[1]), "\"'");
    }

    private function setEnvironmentValue(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return (string) preg_replace($pattern, $line, $contents, 1);
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

}
