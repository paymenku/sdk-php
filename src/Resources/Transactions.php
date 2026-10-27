<?php

declare(strict_types=1);

namespace Paymenku\Resources;

use Paymenku\Client;

final class Transactions
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array{
     *   channel_code: string,
     *   amount: int|float,
     *   reference_id: string,
     *   customer_name: string,
     *   customer_email: string,
     *   customer_phone?: string,
     *   return_url: string,
     *   order_items?: array<int, array{name: string, quantity?: int}>
     * } $input
     * @param array{idempotencyKey?: string} $options
     *
     * @return array<string, mixed>
     */
    public function create(array $input, array $options = []): array
    {
        return $this->client->request('POST', '/transaction/create', [
            'body' => $input,
            'idempotencyKey' => $options['idempotencyKey'] ?? null,
        ])['data'];
    }

    /**
     * Cari berdasarkan `trx_id` atau `reference_id`.
     *
     * @return array<string, mixed>
     */
    public function get(string $orderId): array
    {
        return $this->client->request(
            'GET',
            '/check-status/' . rawurlencode($orderId),
        )['data'];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function list(array $params = []): array
    {
        $response = $this->client->request('GET', '/transactions', ['query' => $params]);

        return [
            'data' => $response['data'] ?? [],
            'pagination' => $response['body']['pagination'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function cancel(string $trxId): array
    {
        $response = $this->client->request('POST', '/transaction/cancel', [
            'body' => ['trx_id' => $trxId],
        ]);

        return $this->withMessage($response['data'], $response['body']);
    }

    /** @return array<string, mixed> */
    public function refund(string $trxId, int|float $refundAmount, ?string $reason = null): array
    {
        $response = $this->client->request('POST', '/transaction/refund', [
            'body' => [
                'trx_id' => $trxId,
                'refund_amount' => $refundAmount,
                'reason' => $reason,
            ],
        ]);

        return $this->withMessage($response['data'], $response['body']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function withMessage(array $data, ?array $body): array
    {
        $message = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : null;

        return $message === null ? $data : $data + ['message' => $message];
    }
}
