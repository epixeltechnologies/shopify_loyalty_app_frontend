<?php

namespace Tests\Support;

/**
 * Shared helper for every webhook test — builds a real HMAC-signed
 * request body/headers so tests exercise the actual
 * VerifyShopifyWebhook middleware rather than bypassing it. Extracted
 * here once other test files needed the same signing logic
 * `WebhookIdempotencyTest` already had inline, rather than duplicating it.
 */
trait SignsShopifyWebhooks
{
    /** @return array{0: string, 1: string} [rawBody, hmacHeaderValue] */
    private function signedPayload(array $payload): array
    {
        $body = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $body, config('shopify.api_secret'), true));

        return [$body, $hmac];
    }

    /** @return array<string, string> */
    private function webhookHeaders(string $hmac, ?string $webhookId = null, ?string $shopDomain = null): array
    {
        return array_filter([
            'X-Shopify-Hmac-Sha256' => $hmac,
            'X-Shopify-Webhook-Id' => $webhookId,
            'X-Shopify-Shop-Domain' => $shopDomain,
            'Content-Type' => 'application/json',
        ], fn ($v) => $v !== null);
    }

    private function postWebhook(string $topic, array $payload, ?string $webhookId = null, ?string $shopDomain = null)
    {
        [$body, $hmac] = $this->signedPayload($payload);

        return $this->call(
            'POST',
            "/webhooks/{$topic}",
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->webhookHeaders($hmac, $webhookId, $shopDomain)),
            $body,
        );
    }
}
