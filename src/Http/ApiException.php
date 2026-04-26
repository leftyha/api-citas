<?php

declare(strict_types=1);

namespace Booking\Http;

final class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'INTERNAL_ERROR',
        private readonly int $statusCode = 500,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
