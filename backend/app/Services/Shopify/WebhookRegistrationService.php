<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Registers every topic in `config('shopify.webhooks')` against
 * Shopify's Admin GraphQL API (`webhookSubscriptionCreate` — the
 * current, non-deprecated mechanism; the older REST
 * `POST /admin/webhooks.json` endpoint is not used). Called once after
 * a successful OAuth callback (fresh install) and again on every
 * reinstall, since Shopify does not carry webhook subscriptions forward
 * across an uninstall/reinstall cycle.
 *
 * The three GDPR/"mandatory compliance" topics
 * (`customers/data_request`, `customers/redact`, `shop/redact`) are
 * deliberately EXCLUDED from this API-based registration — Shopify
 * requires those to be configured once, app-wide, in the Partner
 * Dashboard's compliance webhooks section, not per-shop via
 * `webhookSubscriptionCreate`. They still have job handlers in
 * `config('shopify.webhooks')` (see WebhookController, which dispatches
 * by topic regardless of how the subscription was created) — only the
 * *registration* step differs. See docs/DEVELOPMENT.md's Partner
 * Dashboard checklist.
 *
 * Idempotent: `webhookSubscriptionCreate` returns a `userError` for a
 * topic/callback URL pair that already exists rather than creating a
 * duplicate, so calling this repeatedly (e.g. a retried queue job) is
 * safe — a "already exists" userError is logged at `info`, not treated
 * as a failure.
 */
class WebhookRegistrationService
{
    /** Configured app-wide in the Partner Dashboard, never via the API — see class docblock. */
    private const COMPLIANCE_TOPICS = ['customers/redact', 'shop/redact', 'customers/data_request'];

    public function __construct(private readonly ShopifyGraphQLClient $client) {}

    public function registerAll(Shop $shop): void
    {
        $topics = array_diff(array_keys(config('shopify.webhooks', [])), self::COMPLIANCE_TOPICS);

        foreach ($topics as $topic) {
            try {
                $this->register($shop, $topic);
            } catch (\Throwable $e) {
                // One topic failing (rate limit, transient Shopify outage)
                // must never abort registration of the remaining topics,
                // and — since this runs inside a queued job dispatched
                // synchronously in tests/local (QUEUE_CONNECTION=sync) —
                // must never propagate up into whatever dispatched this
                // job. In production (QUEUE_CONNECTION=redis) an
                // uncaught exception here would already be isolated by
                // the queue worker process; this catch makes that
                // isolation guarantee hold in every environment, not just
                // production.
                Log::error('Webhook subscription registration threw an exception', [
                    'shop' => $shop->shopify_domain,
                    'topic' => $topic,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    private function register(Shop $shop, string $topic): void
    {
        $mutation = <<<'GQL'
            mutation WebhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
              webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                webhookSubscription { id }
                userErrors { field message }
              }
            }
        GQL;

        $data = $this->client->query($shop, $mutation, [
            'topic' => $this->toGraphQLTopic($topic),
            'webhookSubscription' => [
                'callbackUrl' => config('app.url')."/webhooks/{$topic}",
                'format' => 'JSON',
            ],
        ]);

        $userErrors = $data['webhookSubscriptionCreate']['userErrors'] ?? [];

        if (! empty($userErrors)) {
            $messages = collect($userErrors)->pluck('message')->implode('; ');

            if (str_contains(strtolower($messages), 'already exists') || str_contains(strtolower($messages), 'has already been taken')) {
                Log::info('Webhook subscription already registered', ['shop' => $shop->shopify_domain, 'topic' => $topic]);

                return;
            }

            Log::warning('Webhook subscription registration failed', [
                'shop' => $shop->shopify_domain,
                'topic' => $topic,
                'errors' => $messages,
            ]);
        }
    }

    /** 'orders/paid' -> 'ORDERS_PAID'; Shopify's GraphQL enum format for webhook topics. */
    private function toGraphQLTopic(string $configTopic): string
    {
        return strtoupper(str_replace(['/', '-'], '_', $configTopic));
    }
}
