<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PortalAttendanceController;
use App\Http\Controllers\RecognitionBridgeController;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SystemApiSettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::middleware(['attendance.kiosk'])->group(function (): void {
    Route::view('/attendance-station', 'attendance.kiosk')->name('attendance.kiosk');
    Route::get('/attendance-station/status', [RecognitionBridgeController::class, 'kioskStatus'])
        ->middleware('throttle:120,1')->name('attendance.kiosk.status');
    Route::post('/attendance-station/scan', [RecognitionBridgeController::class, 'recognize'])
        ->middleware('throttle:30,1')->name('attendance.kiosk.scan');
    Route::post('/attendance-station/preview', [RecognitionBridgeController::class, 'preview'])
        ->middleware('throttle:180,1')->name('attendance.kiosk.preview');
});
Route::view('/ui-kit', 'ui-kit.index')->name('ui-kit.index');
Route::view('/ui-kit/components', 'ui-kit.components')->name('ui-kit.components');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/settings/profile', [AccountProfileController::class, 'edit'])->name('settings.profile');
    Route::put('/settings/profile', [AccountProfileController::class, 'update'])->name('settings.profile.update');
    Route::put('/settings/profile/password', [AccountProfileController::class, 'updatePassword'])->name('settings.profile.password');
    Route::get('/my-attendance', PortalAttendanceController::class)
        ->middleware('role:student|faculty|staff')->name('portal.attendance');

    // The camera is operated by teaching/attendance staff.  They can choose a
    // class session and submit a scan, but do not receive administration access.
    Route::middleware('role:super_admin|attendance_admin|faculty|staff')->group(function (): void {
        Route::get('/recognition', [RecognitionController::class, 'index'])->name('recognition');
        Route::get('/recognition/activity', [RecognitionController::class, 'activity'])->name('recognition.activity');
        Route::get('/recognition/python/status', [RecognitionBridgeController::class, 'status'])->name('recognition.python.status');
        Route::post('/recognition/python/scan', [RecognitionBridgeController::class, 'recognize'])->name('recognition.python.scan');
        Route::post('/recognition/python/preview', [RecognitionBridgeController::class, 'preview'])
            ->middleware('throttle:180,1')->name('recognition.python.preview');
    });

    Route::middleware('role:super_admin|attendance_admin|reviewer')->group(function (): void {
        Route::get('/people', [PersonController::class, 'index'])->name('people.index');
        Route::get('/people/{person}/enrollment-shots/{session}/{file}', [PersonController::class, 'enrollmentShot'])
            ->where(['session' => '[0-9a-fA-F-]{36}', 'file' => 'sample-[0-9]{2}\.jpg'])
            ->name('people.enrollment-shots.show');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/sessions', [AttendanceController::class, 'sessions'])->name('attendance.sessions');
        Route::get('/attendance/sessions/{schedule}/{date}', [AttendanceController::class, 'showSession'])
            ->where(['schedule' => '[A-Za-z0-9_-]+', 'date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'])
            ->name('attendance.sessions.show');
        Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::get('/schedules/rooms/suggest', [ScheduleController::class, 'suggestRooms'])->name('schedules.rooms.suggest');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware('role:super_admin|attendance_admin')->group(function (): void {
        Route::view('/settings/recognition-server', 'settings.recognition-server')->name('settings.recognition-server');
        Route::get('/settings/api', [SystemApiSettingsController::class, 'index'])->name('settings.api');
        Route::put('/settings/api', [SystemApiSettingsController::class, 'update'])->name('settings.api.update');
        Route::get('/settings/email-notifications', [MailSettingsController::class, 'index'])->name('settings.email-notifications');
        Route::put('/settings/email-notifications', [MailSettingsController::class, 'update'])->name('settings.email-notifications.update');
        Route::post('/settings/email-notifications/test', [MailSettingsController::class, 'sendTest'])->name('settings.email-notifications.test');

        Route::get('/people/create', [PersonController::class, 'create'])->name('people.create');
        Route::post('/people', [PersonController::class, 'store'])->name('people.store');
        Route::get('/people/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');
        Route::get('/people/{person}/facial-samples/{facialProfile}', [PersonController::class, 'facialSample'])->name('people.facial-samples.show');
        Route::put('/people/{person}', [PersonController::class, 'update'])->name('people.update');
        Route::delete('/people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');

        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::put('/attendance/{attendanceRecord}', [AttendanceController::class, 'update'])->name('attendance.update');

        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::put('/schedules/{schedule}/assignments', [ScheduleController::class, 'assign'])->name('schedules.assign');
        Route::post('/departments', [ReferenceDataController::class, 'department'])->name('departments.store');
        Route::post('/locations', [ReferenceDataController::class, 'location'])->name('locations.store');

        Route::put('/recognition/events/{recognitionEvent}/review', [RecognitionController::class, 'review'])->name('recognition.review');
        Route::post('/recognition/python/start', [RecognitionBridgeController::class, 'start'])->name('recognition.python.start');
        Route::post('/recognition/python/backend', [RecognitionBridgeController::class, 'switchBackend'])->name('recognition.python.backend');
        Route::post('/recognition/python/enroll', [RecognitionBridgeController::class, 'enroll'])->name('recognition.python.enroll');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/attendance-staff', [UserController::class, 'index'])->name('attendance.staff');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
