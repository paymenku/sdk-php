<?php

declare(strict_types=1);

namespace Paymenku\Tests;

use Paymenku\Http\Response;
use Paymenku\Http\TransportInterface;
use RuntimeException;

final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: int}> */
    public array $requests = [];

    /** @var array<int, Response> */
    private array $queue = [];

    public function queue(Response ...$responses): void
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }
    }

    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutSeconds,
    ): Response {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeoutSeconds,
        ];

        if ($this->queue === []) {
            throw new RuntimeException('Tidak ada respons palsu yang disiapkan');
        }

        return array_shift($this->queue);
    }
}
