<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalAttendanceKiosk
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('attendpro.public_attendance.enabled'), 404);
        abort_unless(in_array($request->ip(), config('attendpro.public_attendance.allowed_ips'), true), 403);

        return $next($request);
    }
}
