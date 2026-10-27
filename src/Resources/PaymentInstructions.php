<?php

declare(strict_types=1);

namespace Paymenku\Resources;

use Paymenku\Client;

final class PaymentInstructions
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array{pay_code?: string, amount?: int|float, allow_html?: bool} $options
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(string $code, array $options = []): array
    {
        return $this->client->request('GET', '/payment/instruction', [
            'query' => [
                'code' => $code,
                'pay_code' => $options['pay_code'] ?? null,
                'amount' => $options['amount'] ?? null,
                'allow_html' => isset($options['allow_html'])
                    ? ($options['allow_html'] ? '1' : '0')
                    : null,
            ],
        ])['data'] ?? [];
    }
}
