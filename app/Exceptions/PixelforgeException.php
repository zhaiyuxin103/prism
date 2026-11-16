<?php

namespace App\Exceptions;

use RuntimeException;

class PixelforgeException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public bool $retryable = false,
        public bool $definitiveRejection = false,
        public int $retryAfter = 0,
    ) {
        parent::__construct($message);
    }
}
