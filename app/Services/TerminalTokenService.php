<?php

namespace App\Services;

use App\Models\Terminal;
use Illuminate\Support\Str;

class TerminalTokenService
{
    public function issue(Terminal $terminal): string
    {
        $tokenId = (string) Str::uuid();
        $secret = Str::random(64);

        $terminal->forceFill([
            'api_token_id' => $tokenId,
            'api_token_hash' => hash('sha256', $secret),
        ])->save();

        return $tokenId.'.'.$secret;
    }
}
