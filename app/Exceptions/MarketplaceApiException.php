<?php

namespace App\Exceptions;

use RuntimeException;

class MarketplaceApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
