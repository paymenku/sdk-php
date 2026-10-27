<?php

declare(strict_types=1);

namespace Paymenku\Resources;

use Paymenku\Client;

final class PaymentChannels
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->client->request('GET', '/payment-channels')['data'] ?? [];
    }
}
