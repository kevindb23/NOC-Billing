<?php

namespace App\Services;

use RuntimeException;

class NetworkAutomationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'automation_service_unavailable',
        public readonly int $statusCode = 503,
    ) {
        parent::__construct($message);
    }
}
