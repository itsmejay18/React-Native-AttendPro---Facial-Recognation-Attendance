<?php

namespace App\Exceptions;

use RuntimeException;

class PythonRecognitionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 502,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }
}
