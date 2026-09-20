<?php

return [
    'seed_users' => [
        'super_admin' => [
            'name' => 'Super Administrator',
            'email' => env('ATTENDPRO_ADMIN_EMAIL', 'attendance.admin@hccd.edu.ph'),
            'password' => env('ATTENDPRO_ADMIN_PASSWORD'),
            'local_password' => 'AttendPro!ChangeMe2026',
            'password_environment_variable' => 'ATTENDPRO_ADMIN_PASSWORD',
        ],
        'attendance_admin' => [
            'name' => 'Attendance Staff',
            'email' => env('ATTENDPRO_STAFF_EMAIL', 'attendance.staff@hccd.edu.ph'),
            'password' => env('ATTENDPRO_STAFF_PASSWORD'),
            'local_password' => 'AttendPro!Staff2026',
            'password_environment_variable' => 'ATTENDPRO_STAFF_PASSWORD',
        ],
        'reviewer' => [
            'name' => 'Attendance Reviewer',
            'email' => env('ATTENDPRO_REVIEWER_EMAIL', 'attendance.reviewer@hccd.edu.ph'),
            'password' => env('ATTENDPRO_REVIEWER_PASSWORD'),
            'local_password' => 'AttendPro!Reviewer2026',
            'password_environment_variable' => 'ATTENDPRO_REVIEWER_PASSWORD',
        ],
    ],
    'python_service' => [
        'url' => env('ATTENDPRO_PYTHON_URL', 'http://127.0.0.1:5001'),
        'key' => env('ATTENDPRO_PYTHON_SERVICE_KEY'),
        'timeout' => (int) env('ATTENDPRO_PYTHON_TIMEOUT', 30),
        'managed' => (bool) env('ATTENDPRO_PYTHON_MANAGED', env('APP_ENV', 'production') === 'local'),
        'host' => env('ATTENDPRO_PYTHON_HOST', '127.0.0.1'),
        'port' => (int) env('ATTENDPRO_PYTHON_PORT', 5001),
    ],
    'recognition' => [
        'face_backend' => env('ATTENDPRO_FACE_BACKEND', 'insightface'),
        'minimum_confidence' => (float) env('ATTENDPRO_MIN_CONFIDENCE', 0.65),
        'minimum_margin' => (float) env('ATTENDPRO_MIN_SECOND_BEST_MARGIN', 0.04),
        'frame_window' => (int) env('ATTENDPRO_FRAME_WINDOW', 7),
        'min_consistent_matches' => (int) env('ATTENDPRO_MIN_CONSISTENT_MATCHES', 5),
        // Enrollment always captures a 15-image reference set. It is not a
        // quality-count gate: every frame with one detected face is stored.
        'enrollment_samples' => 15,
        'retain_enrollment_images' => true,
        'face_sync_page_size' => (int) env('ATTENDPRO_FACE_SYNC_PAGE_SIZE', 250),
        'duplicate_scan_seconds' => (int) env('ATTENDPRO_DUPLICATE_SCAN_SECONDS', 60),
    ],
    'notifications' => [
        'attendance_email' => (bool) env('ATTENDPRO_ATTENDANCE_EMAIL_NOTIFICATIONS', true),
    ],
    'public_attendance' => [
        'enabled' => (bool) env('ATTENDPRO_PUBLIC_ATTENDANCE_ENABLED', true),
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ATTENDPRO_PUBLIC_ATTENDANCE_ALLOWED_IPS', '127.0.0.1,::1')),
        ))),
    ],
    'terminal' => [
        'allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ATTENDPRO_TERMINAL_ALLOWED_IPS', '')),
        ))),
        'offline_after_minutes' => (int) env('ATTENDPRO_TERMINAL_OFFLINE_AFTER', 5),
    ],
];
