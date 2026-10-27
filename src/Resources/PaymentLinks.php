<?php

declare(strict_types=1);

namespace Paymenku\Resources;

use Paymenku\Client;

final class PaymentLinks
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @param array{idempotencyKey?: string} $options
     *
     * @return array<string, mixed>
     */
    public function create(array $input, array $options = []): array
    {
        return $this->client->request('POST', '/payment-link/create', [
            'body' => $input,
            'idempotencyKey' => $options['idempotencyKey'] ?? null,
        ])['data'];
    }

    /** @return array<string, mixed> */
    public function get(string $linkId): array
    {
        return $this->client->request(
            'GET',
            '/payment-link/' . rawurlencode($linkId),
        )['data'];
    }
}
