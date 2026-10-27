<?php

declare(strict_types=1);

namespace Paymenku;

final class Webhook
{
    /**
     * Verifikasi header `X-PaymenKu-Signature`.
     * Rumus: HMAC-SHA256(timestamp + "." + rawBody, secret) dalam hex.
     *
     * @param string $rawBody Body mentah persis seperti diterima (belum di-parse).
     */
    public static function verify(
        string $rawBody,
        string $timestamp,
        string $signature,
        string $secret,
    ): bool {
        if ($timestamp === '' || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
