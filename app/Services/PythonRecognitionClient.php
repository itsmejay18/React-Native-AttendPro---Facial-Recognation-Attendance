<?php

namespace App\Services;

use App\Exceptions\PythonRecognitionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class PythonRecognitionClient
{
    public function health(): array
    {
        return $this->send(fn () => $this->client()->get($this->url('/v1/health')));
    }

    public function recognize(UploadedFile $image, string $direction): array
    {
        return $this->send(fn () => $this->client()
            ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName(), [
                'Content-Type' => $image->getMimeType(),
            ])
            ->post($this->url('/v1/recognize'), ['direction' => $direction]));
    }

    /** @param array<int, UploadedFile> $images */
    public function recognizeBatch(array $images, string $direction): array
    {
        return $this->send(function () use ($images, $direction): Response {
            $request = $this->client();
            foreach ($images as $image) {
                $request = $request->attach('images', file_get_contents($image->getRealPath()), $image->getClientOriginalName(), [
                    'Content-Type' => $image->getMimeType(),
                ]);
            }

            return $request->post($this->url('/v1/recognize-batch'), ['direction' => $direction]);
        });
    }

    public function extract(UploadedFile $image): array
    {
        return $this->send(fn () => $this->client()
            ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName(), [
                'Content-Type' => $image->getMimeType(),
            ])
            ->post($this->url('/v1/extract')));
    }

    public function preview(UploadedFile $image): array
    {
        return $this->send(fn () => $this->client()
            ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName(), [
                'Content-Type' => $image->getMimeType(),
            ])
            ->post($this->url('/v1/preview')));
    }

    /** @param array<int, UploadedFile> $images */
    public function extractBatch(array $images): array
    {
        return $this->send(function () use ($images): Response {
            $request = $this->client();
            foreach ($images as $image) {
                $request = $request->attach('images', file_get_contents($image->getRealPath()), $image->getClientOriginalName(), [
                    'Content-Type' => $image->getMimeType(),
                ]);
            }

            return $request->post($this->url('/v1/extract-batch'));
        });
    }

    public function indexProfile(array $profile): array
    {
        return $this->send(fn () => $this->client()->post($this->url('/v1/index-profile'), $profile));
    }

    /** @param array<int, array<string, mixed>> $profiles */
    public function indexProfiles(array $profiles): array
    {
        return $this->send(fn () => $this->client()->post($this->url('/v1/index-profiles'), $profiles));
    }

    public function enroll(
        UploadedFile $image,
        string $institutionId,
        string $consentedAt,
        ?string $retentionUntil,
        int $enrolledBy,
    ): array {
        return $this->send(fn () => $this->client()
            ->attach('image', file_get_contents($image->getRealPath()), $image->getClientOriginalName(), [
                'Content-Type' => $image->getMimeType(),
            ])
            ->post($this->url('/v1/enroll'), array_filter([
                'institution_id' => $institutionId,
                'consented_at' => $consentedAt,
                'retention_until' => $retentionUntil,
                'enrolled_by' => $enrolledBy,
            ], fn ($value) => $value !== null && $value !== '')));
    }

    private function client(): PendingRequest
    {
        $serviceKey = (string) config('attendpro.python_service.key');
        if ($serviceKey === '') {
            throw new PythonRecognitionException('ATTENDPRO_PYTHON_SERVICE_KEY is not configured.', 503);
        }

        return Http::acceptJson()
            ->withHeaders(['X-AttendPro-Service-Key' => $serviceKey])
            ->timeout((int) config('attendpro.python_service.timeout', 30))
            ->connectTimeout(3);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('attendpro.python_service.url'), '/').$path;
    }

    /** @param callable(): Response $request */
    private function send(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new PythonRecognitionException(
                'The Python facial-recognition service is offline. Start it and verify its configured URL.',
                503,
            );
        }

        $body = $response->json();
        if (! $response->successful()) {
            throw new PythonRecognitionException(
                is_array($body) ? ($body['message'] ?? 'The Python recognition service rejected the request.') : 'The Python recognition service returned an invalid response.',
                in_array($response->status(), [400, 401, 403, 413, 422, 503], true) ? $response->status() : 502,
                is_array($body) ? $body : null,
            );
        }
        if (! is_array($body)) {
            throw new PythonRecognitionException('The Python recognition service returned invalid JSON.', 502);
        }

        return $body;
    }
}
