<?php

namespace App\Services;

use App\Exceptions\PythonRecognitionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class PythonServiceManager
{
    public function __construct(private readonly PythonRecognitionClient $client) {}

    public function start(): array
    {
        $lock = Cache::lock('attendpro:python-service-start', 20);
        if (! $lock->get()) {
            throw new PythonRecognitionException('The Python recognition server is already being started.', 409);
        }

        try {
            if ($health = $this->health()) {
                return $health + ['already_running' => true];
            }

            $this->assertStartable();

            if ($this->portIsOpen()) {
                throw new PythonRecognitionException(
                    'Port 5001 is already in use by another process. Close it before starting facial recognition.',
                    409,
                );
            }

            $result = Process::path(base_path())
                ->timeout(15)
                ->run([
                    'powershell.exe',
                    '-NoProfile',
                    '-ExecutionPolicy',
                    'Bypass',
                    '-File',
                    base_path('scripts/start-python-service.ps1'),
                ]);

            if (! $result->successful()) {
                throw new PythonRecognitionException(
                    'Laravel could not start the Python recognition server. '.$this->processError($result->errorOutput()),
                    503,
                );
            }

            $pid = (int) trim($result->output());
            if ($pid > 0) {
                File::ensureDirectoryExists(storage_path('app/attendpro'));
                File::put(storage_path('app/attendpro/python-service.pid'), (string) $pid);
            }

            for ($attempt = 0; $attempt < 40; $attempt++) {
                usleep(250_000);
                if ($health = $this->health()) {
                    if (($health['status'] ?? null) === 'ready') {
                        return $health + ['already_running' => false, 'managed_pid' => $pid ?: null];
                    }
                }
            }

            if ($health ?? null) {
                return $health + ['already_running' => false, 'managed_pid' => $pid ?: null];
            }

            throw new PythonRecognitionException(
                'Python was launched but did not answer at http://127.0.0.1:5001. Review storage/logs/python-service-error.log.',
                503,
            );
        } finally {
            $lock->release();
        }
    }

    public function restart(): array
    {
        $this->stop();

        return $this->start();
    }

    private function health(): ?array
    {
        try {
            $health = data_get($this->client->health(), 'data');

            return is_array($health) ? $health : null;
        } catch (PythonRecognitionException) {
            return null;
        }
    }

    private function assertStartable(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new PythonRecognitionException('Managed Python startup is currently available only on Windows.', 503);
        }

        if ((string) config('attendpro.python_service.key') === '') {
            throw new PythonRecognitionException('Run php artisan attendpro:recognition-setup before starting Python.', 503);
        }

        foreach ([
            base_path('python_service/.venv/Scripts/python.exe'),
            base_path('scripts/start-python-service.ps1'),
        ] as $requiredPath) {
            if (! is_file($requiredPath)) {
                throw new PythonRecognitionException(
                    'Python recognition setup is incomplete. Double-click start-attendpro.bat once to install it.',
                    503,
                );
            }
        }

        if (! in_array(config('attendpro.recognition.face_backend', 'insightface'), ['dlib', 'insightface'], true)) {
            foreach ([
                base_path('python_service/models/face_detection_yunet_2023mar.onnx'),
                base_path('python_service/models/face_recognition_sface_2021dec.onnx'),
            ] as $modelPath) {
                if (! is_file($modelPath)) {
                    throw new PythonRecognitionException(
                        'Python recognition setup is incomplete. Double-click start-attendpro.bat once to install it.',
                        503,
                    );
                }
            }
        }
    }

    private function stop(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new PythonRecognitionException('Managed Python restart is currently available only on Windows.', 503);
        }

        $port = (int) config('attendpro.python_service.port', 5001);
        $command = '$listeners = Get-NetTCPConnection -LocalPort '.$port.' -State Listen -ErrorAction SilentlyContinue; '
            .'$listeners | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }';

        $result = Process::timeout(10)->run([
            'powershell.exe',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $command,
        ]);

        if (! $result->successful()) {
            throw new PythonRecognitionException('Laravel could not stop the current Python recognition server.', 503);
        }

        for ($attempt = 0; $attempt < 20 && $this->portIsOpen(); $attempt++) {
            usleep(150_000);
        }

        if ($this->portIsOpen()) {
            throw new PythonRecognitionException('The Python recognition server did not stop. Close the process using port '.$port.' and try again.', 503);
        }

        File::delete(storage_path('app/attendpro/python-service.pid'));
    }

    private function portIsOpen(): bool
    {
        $connection = @fsockopen(
            (string) config('attendpro.python_service.host', '127.0.0.1'),
            (int) config('attendpro.python_service.port', 5001),
            $errorCode,
            $errorMessage,
            0.25,
        );

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    private function processError(string $error): string
    {
        $message = trim($error);

        return $message !== '' ? str($message)->limit(300)->toString() : 'No process error was returned.';
    }
}
