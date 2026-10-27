<?php

declare(strict_types=1);

namespace Paymenku\Exception;

use RuntimeException;
use Throwable;

/**
 * Galat dari API Paymenku (HTTP 4xx/5xx atau envelope error).
 */
final class ApiException extends RuntimeException
{
    /** @param array<string, array<int, string>>|null $errors */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?array $errors = null,
        private readonly ?string $errorCode = null,
        private readonly mixed $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, array<int, string>>|null */
    public function errors(): ?array
    {
        return $this->errors;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function responseBody(): mixed
    {
        return $this->responseBody;
    }
}
