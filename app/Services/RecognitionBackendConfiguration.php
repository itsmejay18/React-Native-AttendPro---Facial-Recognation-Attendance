<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class RecognitionBackendConfiguration
{
    public const BACKENDS = [
        'sface' => [
            'name' => 'OpenCV YuNet Face Detector + OpenCV SFace Face Recognition',
            'model' => 'face_detection_yunet_2023mar.onnx + face_recognition_sface_2021dec.onnx',
        ],
        'dlib' => [
            'name' => 'dlib face_recognition ResNet v1 Face Recognition',
            'model' => 'dlib ResNet v1 (99.38% LFW) + HOG face detector',
        ],
        'insightface' => [
            'name' => 'InsightFace buffalo_l SCRFD + ArcFace R100',
            'model' => 'buffalo_l pack (SCRFD-10G detector + ArcFace R100 512-d)',
        ],
    ];

    public function current(): string
    {
        $backend = strtolower((string) config('attendpro.recognition.face_backend', 'insightface'));

        return array_key_exists($backend, self::BACKENDS) ? $backend : 'insightface';
    }

    public function set(string $backend): void
    {
        if (! array_key_exists($backend, self::BACKENDS)) {
            throw new \InvalidArgumentException('Unsupported recognition backend.');
        }

        $environmentPath = base_path('.env');
        $contents = File::exists($environmentPath) ? File::get($environmentPath) : '';
        $line = "ATTENDPRO_FACE_BACKEND={$backend}";

        if (preg_match('/^ATTENDPRO_FACE_BACKEND=.*$/m', $contents)) {
            $contents = preg_replace('/^ATTENDPRO_FACE_BACKEND=.*$/m', $line, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents).PHP_EOL.PHP_EOL.$line.PHP_EOL;
        }

        File::put($environmentPath, $contents);
        Artisan::call('config:clear');
        config(['attendpro.recognition.face_backend' => $backend]);
        putenv("ATTENDPRO_FACE_BACKEND={$backend}");
        $_ENV['ATTENDPRO_FACE_BACKEND'] = $backend;
        $_SERVER['ATTENDPRO_FACE_BACKEND'] = $backend;
    }
}
