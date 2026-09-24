<?php

namespace App\Exceptions\Shopify;

use RuntimeException;

class InvalidHmacException extends RuntimeException
{
    public function __construct(string $message = 'HMAC verification failed.')
    {
        parent::__construct($message);
    }
}
