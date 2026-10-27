# paymenku/sdk-php

SDK PHP untuk [API Paymenku](https://paymenku.com). Tanpa dependensi runtime —
memakai cURL bila tersedia, dengan fallback stream HTTP. PHP 8.1+.

## Instalasi

```bash
composer require paymenku/sdk-php
```

## Mulai cepat

```php
<?php

require 'vendor/autoload.php';

use Paymenku\Client;

$client = new Client(getenv('PAYMENKU_API_KEY'));

$trx = $client->transactions->create([
    'channel_code'   => 'qris',
    'amount'         => 15000,
    'reference_id'   => 'INV-20261027-001',
    'customer_name'  => 'Budi Santoso',
    'customer_email' => 'budi@example.com',
    'return_url'     => 'https://tokoanda.com/selesai',
]);

echo $trx['trx_id'], PHP_EOL;
echo $trx['pay_url'], PHP_EOL;
echo $trx['payment_info']['qr_url'], PHP_EOL;
```

Kunci `sk_test_...` membuat transaksi sandbox; `sk_live_...` memakai dana nyata
dan memerlukan KYC yang disetujui.

## Transaksi

```php
// Cek status berdasarkan trx_id atau reference_id
$trx = $client->transactions->get('INV-20261027-001');

// Daftar transaksi
$hasil = $client->transactions->list([
    'status'     => 'paid',
    'per_page'   => 50,
    'date_from'  => '2026-10-01',
    'is_sandbox' => false,
]);
$hasil['data'];
$hasil['pagination'];

// Batalkan transaksi pending
$client->transactions->cancel($trx['trx_id']);

// Refund transaksi paid
$client->transactions->refund($trx['trx_id'], 25950, 'Pesanan dibatalkan');
```

## Kanal, fee, dan instruksi

```php
$channels = $client->paymentChannels->list();

$fee = $client->fees->calculate(100000, 'qris');

$langkah = $client->paymentInstructions->get('bca_va', [
    'pay_code'   => '1234567890',
    'amount'     => 100000,
    'allow_html' => false,
]);
```

## Payment link

```php
$link = $client->paymentLinks->create([
    'amount'             => 150000,
    'title'              => 'Invoice #1024',
    'customer_name'      => 'Budi Santoso',
    'customer_email'     => 'budi@example.com',
    'expires_in_minutes' => 1440,
    'is_single_use'      => true,
]);

$detail = $client->paymentLinks->get($link['link_id']);
```

Payment link hanya menerima kunci live (`sk_live_`).

## Wallet

```php
$saldo   = $client->wallet->balance();
$kliring = $client->wallet->pendingBalance();
```

## Idempotensi

```php
$client->transactions->create($input, ['idempotencyKey' => 'INV-20261027-001']);
```

Kunci yang sama dalam 24 jam memutar ulang respons tersimpan, bukan membuat
transaksi baru.

## Galat

```php
use Paymenku\Exception\ApiException;
use Paymenku\Exception\NetworkException;

try {
    $client->transactions->create($input);
} catch (ApiException $e) {
    $e->statusCode();   // 422
    $e->getMessage();   // Validation failed
    $e->errors();       // ['amount' => ['validation.min']]
    $e->errorCode();
    $e->responseBody();
} catch (NetworkException $e) {
    // timeout, koneksi gagal, atau respons bukan JSON
}
```

`ApiException` juga dilempar untuk envelope `status: "error"` /
`success: false` meskipun HTTP 200.

SDK tidak melakukan percobaan ulang otomatis. Untuk `429` hormati
`Retry-After`; untuk `5xx` ulangi dengan backoff dan `Idempotency-Key`.

## Webhook

Verifikasi header `X-PaymenKu-Signature` dengan body **mentah**:

```php
use Paymenku\Webhook;

$raw = file_get_contents('php://input');
$valid = Webhook::verify(
    $raw,
    $_SERVER['HTTP_X_PAYMENKU_TIMESTAMP'] ?? '',
    $_SERVER['HTTP_X_PAYMENKU_SIGNATURE'] ?? '',
    getenv('PAYMENKU_WEBHOOK_SECRET'),
);

if (! $valid) {
    http_response_code(401);
    exit;
}

http_response_code(200);
// proses setelah response terkirim; idempoten per trx_id
```

Jangan parse JSON lalu serialisasi ulang sebelum verifikasi — urutan kunci dan
spasi berubah dan tanda tangan tidak cocok.

## Konfigurasi

```php
$client = new Client($apiKey, [
    'baseUrl'   => 'https://paymenku.com/api/v1', // bawaan
    'timeout'   => 30,                            // detik
    'transport' => new \Paymenku\Http\CurlTransport(), // opsional
]);
```

## Catatan

- Nominal pada respons berupa **string desimal** (`"15305.00"`).
- `payment_info` berbeda per kanal; `transaction_id` tidak selalu ada.
- Status resmi datang dari webhook, bukan polling.
- Header rate limit tersedia lewat `$client->request(...)['headers']`.

## Pengembangan

```bash
composer install
composer test
```

Lisensi MIT.
