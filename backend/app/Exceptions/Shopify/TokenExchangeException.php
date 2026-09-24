<?php

namespace App\Exceptions\Shopify;

use RuntimeException;

/**
 * Thrown when Shopify's `/admin/oauth/access_token` endpoint fails or
 * returns an unexpected shape. Never includes the request/response body
 * in its message — see ShopifyOAuthService, which logs failure details
 * separately with the token/code redacted, never in an exception
 * message that could end up in a browser-visible error page.
 */
class TokenExchangeException extends RuntimeException
{
    public static function requestFailed(int $status): self
    {
        return new self("Shopify token exchange failed with status {$status}.");
    }

    public static function malformedResponse(): self
    {
        return new self('Shopify token exchange returned an unexpected response.');
    }
}
