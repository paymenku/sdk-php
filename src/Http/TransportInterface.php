<?php

declare(strict_types=1);

namespace Paymenku\Http;

interface TransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutSeconds,
    ): Response;
}
