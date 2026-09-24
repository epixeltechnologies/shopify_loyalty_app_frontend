<?php

namespace App\Exceptions\Shopify;

use RuntimeException;

/**
 * Thrown for any OAuth `state` validation failure — expired, missing
 * (already consumed / replayed), or mismatched. Kept as one exception
 * type with a `reason` rather than three separate classes, since every
 * call site treats all three identically (reject the callback with a
 * 401) — the reason exists for logging/test-assertion granularity, not
 * because callers branch on it.
 */
class OAuthStateException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function expiredOrMissing(): self
    {
        return new self('expired_or_missing', 'OAuth state is missing or has expired. Please restart the installation.');
    }

    public static function mismatch(): self
    {
        return new self('mismatch', 'OAuth state does not match — possible CSRF or replay attempt.');
    }
}
