<?php

declare(strict_types=1);

namespace Paymenku\Tests;

use Paymenku\Client;
use Paymenku\Exception\ApiException;
use Paymenku\Exception\NetworkException;
use Paymenku\Http\Response;
use Paymenku\Webhook;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private FakeTransport $transport;
    private Client $client;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->client = new Client('sk_test_example', [
            'baseUrl' => 'https://example.test/api/v1',
            'transport' => $this->transport,
            'timeout' => 12,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): Response
    {
        return new Response($status, ['content-type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testCreateMengirimBodyHeaderDanMengembalikanData(): void
    {
        $this->transport->queue($this->json(200, [
            'status' => 'success',
            'data' => [
                'trx_id' => 'IDP1',
                'reference_id' => 'INV-1',
                'amount' => '15305.00',
                'status' => 'pending',
                'pay_url' => 'https://paymenku.com/pay/IDP1',
                'payment_info' => ['qr_url' => 'https://paymenku.com/api/qris/IDP1'],
            ],
        ]));

        $result = $this->client->transactions->create([
            'channel_code' => 'qris',
            'amount' => 15000,
            'reference_id' => 'INV-1',
            'customer_name' => 'Budi',
            'customer_email' => 'budi@example.com',
            'return_url' => 'https://tokoanda.com/selesai',
        ], ['idempotencyKey' => 'INV-1']);

        $request = $this->transport->requests[0];

        self::assertSame('POST', $request['method']);
        self::assertSame('https://example.test/api/v1/transaction/create', $request['url']);
        self::assertSame('Bearer sk_test_example', $request['headers']['Authorization']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertSame('INV-1', $request['headers']['Idempotency-Key']);
        self::assertSame(12, $request['timeout']);

        $body = json_decode((string) $request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(15000, $body['amount']);
        self::assertSame('qris', $body['channel_code']);

        self::assertSame('IDP1', $result['trx_id']);
        self::assertSame('15305.00', $result['amount']);
    }

    public function testGalat422MenjadiApiExceptionDenganErrors(): void
    {
        $this->transport->queue($this->json(422, [
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => ['amount' => ['validation.min']],
        ]));

        try {
            $this->client->transactions->create([
                'channel_code' => 'qris',
                'amount' => 1,
                'reference_id' => 'INV-1',
                'customer_name' => 'Budi',
                'customer_email' => 'budi@example.com',
                'return_url' => 'https://tokoanda.com/selesai',
            ]);
            self::fail('ApiException tidak dilempar');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->statusCode());
            self::assertSame('Validation failed', $exception->getMessage());
            self::assertSame(['amount' => ['validation.min']], $exception->errors());
        }
    }

    public function testEnvelopeErrorPadaHttp200TetapDianggapGalat(): void
    {
        $this->transport->queue($this->json(200, [
            'success' => false,
            'message' => 'Wallet not found',
        ]));

        $this->expectException(ApiException::class);
        $this->client->wallet->balance();
    }

    public function testListMengirimQueryBooleanDanPagination(): void
    {
        $this->transport->queue($this->json(200, [
            'status' => 'success',
            'data' => [],
            'pagination' => [
                'current_page' => 2,
                'per_page' => 5,
                'total' => 12,
                'total_pages' => 3,
                'has_more' => true,
            ],
        ]));

        $result = $this->client->transactions->list([
            'status' => 'paid',
            'per_page' => 5,
            'is_sandbox' => true,
        ]);

        $url = $this->transport->requests[0]['url'];
        self::assertStringContainsString('status=paid', $url);
        self::assertStringContainsString('per_page=5', $url);
        self::assertStringContainsString('is_sandbox=true', $url);
        self::assertSame(3, $result['pagination']['total_pages']);
    }

    public function testCancelRefundDanStatus(): void
    {
        $this->transport->queue(
            $this->json(200, [
                'status' => 'success',
                'message' => 'Transaction cancelled successfully',
                'data' => ['trx_id' => 'IDP1', 'status' => 'cancelled'],
            ]),
            $this->json(200, [
                'status' => 'success',
                'message' => 'Transaction refunded successfully',
                'data' => ['trx_id' => 'IDP1', 'status' => 'refunded', 'refund_amount' => 25950],
            ]),
            $this->json(200, [
                'status' => 'success',
                'data' => ['trx_id' => 'IDP1', 'status' => 'pending'],
            ]),
        );

        $cancelled = $this->client->transactions->cancel('IDP1');
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame('Transaction cancelled successfully', $cancelled['message']);

        $refunded = $this->client->transactions->refund('IDP1', 25950, 'Pesanan dibatalkan');
        self::assertSame(25950, $refunded['refund_amount']);
        $refundBody = json_decode((string) $this->transport->requests[1]['body'], true);
        self::assertSame('Pesanan dibatalkan', $refundBody['reason']);

        $status = $this->client->transactions->get('INV/1');
        self::assertSame('pending', $status['status']);
        self::assertSame(
            'https://example.test/api/v1/check-status/INV%2F1',
            $this->transport->requests[2]['url'],
        );
    }

    public function testKanalFeeDanInstruksi(): void
    {
        $this->transport->queue(
            $this->json(200, [
                'status' => 'success',
                'data' => [['code' => 'qris', 'max_amount' => 10000000]],
            ]),
            $this->json(200, [
                'success' => true,
                'data' => [['code' => 'qris', 'total_fee' => ['customer' => 900]]],
            ]),
            $this->json(200, [
                'success' => true,
                'message' => '',
                'data' => [['title' => 'ATM BCA', 'steps' => ['Masukkan kartu']]],
            ]),
        );

        $channels = $this->client->paymentChannels->list();
        self::assertSame(10000000, $channels[0]['max_amount']);

        $this->client->fees->calculate(100000, 'qris');
        self::assertStringContainsString('amount=100000', $this->transport->requests[1]['url']);
        self::assertStringContainsString('code=qris', $this->transport->requests[1]['url']);

        $this->client->paymentInstructions->get('bca_va', [
            'pay_code' => '123',
            'amount' => 100000,
            'allow_html' => false,
        ]);
        $url = $this->transport->requests[2]['url'];
        self::assertStringContainsString('code=bca_va', $url);
        self::assertStringContainsString('pay_code=123', $url);
        self::assertStringContainsString('allow_html=0', $url);
    }

    public function testPaymentLinkDanWallet(): void
    {
        $this->transport->queue(
            $this->json(201, [
                'status' => 'success',
                'data' => ['link_id' => 'PL1', 'amount' => 150000],
            ]),
            $this->json(200, [
                'status' => 'success',
                'data' => ['link_id' => 'PL1', 'tersedia' => true],
            ]),
            $this->json(200, [
                'success' => true,
                'data' => ['balance' => '36793.00', 'currency' => 'IDR'],
            ]),
            $this->json(200, [
                'success' => true,
                'data' => ['pending_balance' => '0.00', 'clearing_days' => 2],
            ]),
        );

        self::assertSame('PL1', $this->client->paymentLinks->create([
            'amount' => 150000,
            'customer_name' => 'Budi',
            'customer_email' => 'budi@example.com',
        ])['link_id']);

        self::assertTrue($this->client->paymentLinks->get('PL1')['tersedia']);
        self::assertSame('36793.00', $this->client->wallet->balance()['balance']);
        self::assertSame(2, $this->client->wallet->pendingBalance()['clearing_days']);
    }

    public function testWebhookMemverifikasiTandaTangan(): void
    {
        $raw = json_encode(['event' => 'payment.status_updated', 'status' => 'paid'], JSON_THROW_ON_ERROR);
        $timestamp = '1793067181';
        $secret = 'whsec_contoh';
        $signature = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);

        self::assertTrue(Webhook::verify($raw, $timestamp, $signature, $secret));
        self::assertFalse(Webhook::verify($raw, $timestamp, $signature . '0', $secret));
        self::assertFalse(Webhook::verify($raw, $timestamp, '', $secret));
    }

    public function testResponsBukanJsonMenjadiNetworkException(): void
    {
        $this->transport->queue(new Response(502, [], '<html>Bad gateway</html>'));

        $this->expectException(NetworkException::class);
        $this->client->paymentChannels->list();
    }
}
