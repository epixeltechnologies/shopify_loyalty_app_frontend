<?php

namespace App\Services\Shopify;

use App\Exceptions\Shopify\InvalidShopDomainException;

/**
 * Single reusable source of truth for "is this a real Shopify shop
 * domain" — every entry point that receives a shop value from outside
 * the app (the install redirect's `?shop=`, the OAuth callback's
 * `shop` param, a webhook's `X-Shopify-Shop-Domain` header) must run it
 * through here before that value is used to build a URL, run a query,
 * or resolve a tenant.
 *
 * Why this matters (SSRF / host-header attacks): `buildAuthorizeUrl()`
 * and the Admin API client both interpolate the shop domain directly
 * into an outbound HTTPS request URL. Without strict validation, an
 * attacker could pass `shop=attacker.com` or
 * `shop=my-store.myshopify.com.attacker.com` (a classic suffix-spoofing
 * trick) and get the server to make an authenticated-looking request to
 * an arbitrary host, or trick a merchant into an OAuth flow against a
 * domain that only *looks* like their store.
 */
class ShopDomainValidator
{
    /**
     * Real Shopify shop domains are lowercase alphanumeric + hyphens,
     * 1–60 chars per Shopify's own store-handle rules, followed by the
     * exact, unadorned `.myshopify.com` suffix — nothing after it, and
     * no subdomain tricks before it.
     */
    private const PATTERN = '/^[a-z0-9][a-z0-9\-]{0,59}\.myshopify\.com$/';

    /**
     * Validates and normalizes a raw shop value (from a query param or
     * header) into a canonical `store-handle.myshopify.com` string.
     * Throws rather than returning null/false so every call site is
     * forced to handle the failure explicitly instead of accidentally
     * proceeding with an unvalidated value.
     */
    public function validateAndNormalize(?string $rawShop): string
    {
        if (! $rawShop) {
            throw InvalidShopDomainException::malformed('(empty)');
        }

        $normalized = $this->normalize($rawShop);

        if (! preg_match(self::PATTERN, $normalized)) {
            throw InvalidShopDomainException::malformed($rawShop);
        }

        return $normalized;
    }

    public function isValid(?string $rawShop): bool
    {
        try {
            $this->validateAndNormalize($rawShop);

            return true;
        } catch (InvalidShopDomainException) {
            return false;
        }
    }

    /**
     * Lowercases and strips any scheme/path/query a caller might have
     * accidentally included (e.g. a full URL pasted where a bare domain
     * was expected) — normalization happens BEFORE the regex check, so
     * a value can't sneak past validation by hiding behind a scheme the
     * regex wouldn't otherwise match.
     */
    private function normalize(string $rawShop): string
    {
        $value = strtolower(trim($rawShop));

        // Strip a scheme if present, then take only the host portion —
        // parse_url on a bare domain (no scheme) returns null for host,
        // so we special-case that instead of guessing a scheme.
        if (str_contains($value, '://')) {
            $value = parse_url($value, PHP_URL_HOST) ?? $value;
        } else {
            $value = explode('/', $value)[0];
            $value = explode('?', $value)[0];
        }

        return $value;
    }
}
