<?php

declare(strict_types=1);

namespace Paymenku\Resources;

use Paymenku\Client;

final class Fees
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function calculate(int|float $amount, ?string $code = null): array
    {
        return $this->client->request('GET', '/merchant/fee-calculator', [
            'query' => ['amount' => $amount, 'code' => $code],
        ])['data'] ?? [];
    }
}
