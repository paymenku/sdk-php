<?php

declare(strict_types=1);

namespace Paymenku\Exception;

use RuntimeException;
use Throwable;

/**
 * Kegagalan jaringan, timeout, atau respons yang bukan JSON.
 */
final class NetworkException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
