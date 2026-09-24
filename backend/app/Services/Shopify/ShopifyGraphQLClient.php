<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin, typed wrapper around Shopify's Admin GraphQL API. Domain services
 * (BillingService, WebhookRegistrationService, sync services, etc.)
 * depend on this rather than making HTTP calls directly, so
 * authentication, request handling, error handling, rate-limit
 * handling, and retry handling all live in exactly one place — this is
 * the "reusable Shopify API client abstraction" this app's
 * synchronization/billing/webhook-registration services are all built
 * on; no raw Shopify HTTP call exists anywhere else in the codebase.
 *
 * Uses Laravel's `Http` facade (built on Guzzle) rather than
 * instantiating a Guzzle client directly, specifically so
 * `Http::fake()` can intercept these calls in tests — see
 * tests/Unit/Services/Shopify/WebhookRegistrationServiceTest.php and
 * docs/TESTING.md. No test in this app is permitted to call the real
 * Shopify API.
 */
class ShopifyGraphQLClient
{
    public function __construct(private readonly string $apiVersion) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(Shop $shop, string $query, array $variables = []): array
    {
        $attempt = 0;
        $maxRetries = config('shopify.api_rate_limit.max_retries', 5);

        do {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type' => 'application/json',
            ])
                ->timeout(20)
                ->post(
                    "https://{$shop->shopify_domain}/admin/api/{$this->apiVersion}/graphql.json",
                    ['query' => $query, 'variables' => $variables],
                );

            if ($response->status() === 429) {
                $attempt++;
                // Respect Shopify's own Retry-After header when present
                // (it knows its actual bucket refill rate) rather than
                // always guessing via backoff — falls back to
                // exponential backoff only when the header is absent.
                // Never retries more than $maxRetries times, so a
                // persistently rate-limited shop can't retry-storm
                // Shopify or hold a queue worker indefinitely.
                $retryAfterSeconds = (float) ($response->header('Retry-After') ?: (2 ** $attempt * 0.1));
                usleep((int) ($retryAfterSeconds * 1_000_000));

                continue;
            }

            $body = $response->json() ?? [];

            if (! empty($body['errors'])) {
                // Never logs the access token — only the shop domain and
                // the error payload Shopify returned.
                Log::warning('Shopify GraphQL errors', ['shop' => $shop->shopify_domain, 'errors' => $body['errors']]);
                throw new RuntimeException('Shopify GraphQL request returned errors.');
            }

            if ($response->failed()) {
                Log::error('Shopify GraphQL request failed', ['shop' => $shop->shopify_domain, 'status' => $response->status()]);
                throw new RuntimeException("Shopify GraphQL request failed with status {$response->status()}.");
            }

            return $body['data'] ?? [];
        } while ($attempt < $maxRetries);

        throw new RuntimeException('Shopify GraphQL request exhausted retries due to rate limiting.');
    }
}
