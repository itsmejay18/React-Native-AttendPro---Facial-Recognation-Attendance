<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePythonService
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('attendpro.python_service.key');
        $provided = (string) $request->header('X-AttendPro-Service-Key');

        abort_unless($expected !== '' && $provided !== '' && hash_equals($expected, $provided), 401, 'The Python service key is invalid.');

        return $next($request);
    }
}
