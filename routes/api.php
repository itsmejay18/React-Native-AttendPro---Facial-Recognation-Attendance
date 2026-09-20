<?php

use App\Http\Controllers\Api\V1\FaceProfileController;
use App\Http\Controllers\Api\V1\RecognitionEventController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'AttendPro API',
    'version' => 'v1',
    'time' => now()->toIso8601String(),
]));

Route::prefix('v1')->middleware(['python.service', 'throttle:recognition'])->group(function (): void {
    Route::get('/recognition/configuration', fn () => response()->json(['data' => [
        'minimum_confidence' => config('attendpro.recognition.minimum_confidence'),
        'minimum_margin' => config('attendpro.recognition.minimum_margin'),
    ]]));
    Route::get('/faces', [FaceProfileController::class, 'index']);
    Route::post('/faces/enroll', [FaceProfileController::class, 'store']);
    Route::post('/recognition/events', [RecognitionEventController::class, 'store']);
});
