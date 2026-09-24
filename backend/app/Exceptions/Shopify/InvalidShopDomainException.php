<?php

namespace App\Exceptions\Shopify;

use RuntimeException;

/**
 * Thrown by ShopDomainValidator for any shop value that isn't a
 * well-formed `*.myshopify.com` domain. Deliberately a distinct
 * exception type (rather than a generic RuntimeException) so
 * ApiExceptionRenderer / the OAuth controller can render a clear 400
 * instead of a generic 500, and so tests can assert on the specific
 * failure mode.
 */
class InvalidShopDomainException extends RuntimeException
{
    public static function malformed(string $value): self
    {
        return new self("'{$value}' is not a valid Shopify shop domain.");
    }

    public static function untrustedHost(string $value): self
    {
        return new self("'{$value}' does not resolve to a trusted Shopify host.");
    }
}
