<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTerminal
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token || ! str_contains($token, '.')) {
            return $this->unauthorized('A terminal bearer token is required.');
        }

        [$tokenId, $secret] = explode('.', $token, 2);
        $terminal = Terminal::query()->where('api_token_id', $tokenId)->first();

        if (! $terminal || ! $terminal->is_active || ! $terminal->api_token_hash
            || ! hash_equals($terminal->api_token_hash, hash('sha256', $secret))) {
            return $this->unauthorized('The terminal token is invalid or inactive.');
        }

        $allowedIps = config('attendpro.terminal.allowed_ips', []);
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'This terminal IP address is not allowed.'], 403);
        }

        $terminal->timestamps = false;
        $terminal->updateQuietly([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'status' => 'online',
        ]);

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
