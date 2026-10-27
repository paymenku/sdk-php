<?php

declare(strict_types=1);

namespace Paymenku;

use InvalidArgumentException;
use JsonException;
use Paymenku\Exception\ApiException;
use Paymenku\Exception\NetworkException;
use Paymenku\Http\CurlTransport;
use Paymenku\Http\TransportInterface;
use Paymenku\Resources\Fees;
use Paymenku\Resources\PaymentChannels;
use Paymenku\Resources\PaymentInstructions;
use Paymenku\Resources\PaymentLinks;
use Paymenku\Resources\Transactions;
use Paymenku\Resources\Wallet;

/**
 * Klien API Paymenku.
 *
 * ```php
 * $client = new \Paymenku\Client('sk_live_...');
 * $trx = $client->transactions->create([...]);
 * ```
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://paymenku.com/api/v1';
    public const VERSION = '0.1.0';

    public readonly Transactions $transactions;
    public readonly PaymentChannels $paymentChannels;
    public readonly Fees $fees;
    public readonly PaymentInstructions $paymentInstructions;
    public readonly PaymentLinks $paymentLinks;
    public readonly Wallet $wallet;

    private readonly string $baseUrl;
    private readonly int $timeoutSeconds;
    private readonly TransportInterface $transport;
    private readonly string $userAgent;

    /**
     * @param array{
     *   baseUrl?: string,
     *   timeout?: int,
     *   transport?: TransportInterface,
     *   userAgent?: string
     * } $options
     */
    public function __construct(
        private readonly string $apiKey,
        array $options = [],
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('API key Paymenku wajib diisi');
        }

        $this->baseUrl = rtrim($options['baseUrl'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeoutSeconds = max(1, (int) ($options['timeout'] ?? 30));
        $this->transport = $options['transport'] ?? new CurlTransport();
        $this->userAgent = $options['userAgent'] ?? 'paymenku-php/' . self::VERSION;

        $this->transactions = new Transactions($this);
        $this->paymentChannels = new PaymentChannels($this);
        $this->fees = new Fees($this);
        $this->paymentInstructions = new PaymentInstructions($this);
        $this->paymentLinks = new PaymentLinks($this);
        $this->wallet = new Wallet($this);
    }

    /**
     * Escape hatch: kirim permintaan sendiri dan terima envelope lengkap.
     *
     * @param array{
     *   query?: array<string, mixed>,
     *   body?: mixed,
     *   idempotencyKey?: string,
     *   timeout?: int
     * } $options
     *
     * @return array{status: int, headers: array<string, string>, data: mixed, body: mixed}
     */
    public function request(string $method, string $path, array $options = []): array
    {
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException('path harus dimulai dengan "/"');
        }

        $url = $this->baseUrl . $path . $this->buildQuery($options['query'] ?? []);
        $body = null;
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];

        if (array_key_exists('body', $options) && $options['body'] !== null) {
            try {
                $body = json_encode(
                    $options['body'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                );
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Body tidak dapat diubah menjadi JSON', 0, $exception);
            }
            $headers['Content-Type'] = 'application/json';
        }

        if (!empty($options['idempotencyKey'])) {
            $headers['Idempotency-Key'] = (string) $options['idempotencyKey'];
        }

        $response = $this->transport->send(
            strtoupper($method),
            $url,
            $headers,
            $body,
            max(1, (int) ($options['timeout'] ?? $this->timeoutSeconds)),
        );

        $parsed = null;
        if ($response->body !== '') {
            $parsed = json_decode($response->body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NetworkException(
                    sprintf('Respons bukan JSON (HTTP %d)', $response->status),
                );
            }
        }

        $isError = $response->status >= 400
            || (is_array($parsed) && (
                ($parsed['status'] ?? null) === 'error'
                || ($parsed['success'] ?? null) === false
            ));

        if ($isError) {
            $message = is_array($parsed) && is_string($parsed['message'] ?? null)
                ? $parsed['message']
                : sprintf('HTTP %d', $response->status);

            throw new ApiException(
                $message,
                $response->status,
                is_array($parsed) && is_array($parsed['errors'] ?? null) ? $parsed['errors'] : null,
                is_array($parsed) && is_string($parsed['error_code'] ?? null) ? $parsed['error_code'] : null,
                $parsed,
            );
        }

        $data = is_array($parsed) && array_key_exists('data', $parsed) ? $parsed['data'] : $parsed;

        return [
            'status' => $response->status,
            'headers' => $response->headers,
            'data' => $data,
            'body' => $parsed,
        ];
    }

    /** @param array<string, mixed> $query */
    private function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }
}
