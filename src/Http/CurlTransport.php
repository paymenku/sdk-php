<?php

declare(strict_types=1);

namespace Paymenku\Http;

use Paymenku\Exception\NetworkException;

/**
 * Transport bawaan: cURL bila tersedia, jika tidak memakai stream HTTP PHP.
 * Tidak memerlukan dependensi eksternal.
 */
final class CurlTransport implements TransportInterface
{
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutSeconds,
    ): Response {
        if (function_exists('curl_init')) {
            return $this->withCurl($method, $url, $headers, $body, $timeoutSeconds);
        }

        return $this->withStream($method, $url, $headers, $body, $timeoutSeconds);
    }

    private function withCurl(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutSeconds,
    ): Response {
        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new NetworkException('Gagal membuat sesi cURL');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, 'HTTP/')) {
                    return strlen($line);
                }
                $parts = explode(':', $trimmed, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new NetworkException('Gagal menghubungi API Paymenku: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($status, $responseHeaders, (string) $raw);
    }

    private function withStream(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeoutSeconds,
    ): Response {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            $last = error_get_last();
            throw new NetworkException(
                'Gagal menghubungi API Paymenku: ' . ($last['message'] ?? 'kesalahan stream'),
            );
        }

        $responseHeaders = [];
        $status = 0;
        /** @var array<int, string> $http_response_header */
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return new Response($status, $responseHeaders, $raw);
    }
}
