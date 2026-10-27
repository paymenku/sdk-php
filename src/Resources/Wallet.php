<?php

declare(strict_types=1);

namespace Paymenku\Resources;

use Paymenku\Client;

final class Wallet
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return array<string, mixed> */
    public function balance(): array
    {
        return $this->client->request('GET', '/wallet/balance')['data'];
    }

    /** @return array<string, mixed> */
    public function pendingBalance(): array
    {
        return $this->client->request('GET', '/wallet/balance/pending')['data'];
    }
}
