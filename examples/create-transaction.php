<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Paymenku\Client;

$client = new Client(getenv('PAYMENKU_API_KEY') ?: 'sk_test_xxx');

$trx = $client->transactions->create(
    [
        'channel_code' => 'qris',
        'amount' => 15000,
        'reference_id' => 'INV-' . time(),
        'customer_name' => 'Budi Santoso',
        'customer_email' => 'budi@example.com',
        'return_url' => 'https://tokoanda.com/selesai',
    ],
    ['idempotencyKey' => 'INV-' . time()],
);

print_r($trx);
