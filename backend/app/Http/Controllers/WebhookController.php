<?php

namespace App\Http\Controllers;

use App\Exceptions\Shopify\InvalidShopDomainException;
use App\Models\Shop;
use App\Models\Webhook;
use App\Services\Shopify\ShopDomainValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The single entry point for every Shopify webhook topic. Implements
 * the flow docs/WEBHOOKS.md documents in full: verify (VerifyShopifyWebhook,
 * earlier in the middleware chain) -> identify shop -> validate ->
 * store event -> idempotency check -> dispatch queue job -> fast 2xx ack.
 *
 * Deliberately does no topic-specific work itself — see WebhookJob and
 * its subclasses for that — this controller's only job is turning a
 * verified HTTP request into a durable, uniquely-identified `Webhook`
 * row and a queued job, as fast as possible.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly ShopDomainValidator $domainValidator) {}

    public function handle(Request $request, string $topic): JsonResponse
    {
        $rawBody = $request->getContent();
        $webhookId = $request->header('X-Shopify-Webhook-Id');
        $payload = $request->json()->all();

        // Note: VerifyShopifyWebhook (earlier in the middleware chain)
        // HMAC-verifies the request BODY, not this header — Shopify
        // does not sign X-Shopify-Shop-Domain itself. It's normalized/
        // validated here as defense in depth (and used to identify the
        // shop) before being handed to a job, but the real trust
        // boundary for "is this webhook genuinely from Shopify" is the
        // body HMAC, already checked. See docs/WEBHOOKS.md#security.
        $shop = $this->identifyShop($request->header('X-Shopify-Shop-Domain'));
        $payloadHash = hash('sha256', $rawBody);

        [$log, $isNewEvent] = $this->storeEvent($topic, $webhookId, $payload, $payloadHash, $shop);

        if ($isNewEvent) {
            $this->dispatchOrSettle($topic, $log);
        } else {
            Log::info('Duplicate webhook delivery ignored', [
                'webhook_id' => $log->id, 'topic' => $topic, 'shopify_webhook_id' => $webhookId,
            ]);
        }

        // Always 200 quickly — Shopify retries on non-2xx / timeout, and
        // the real work happens asynchronously regardless of whether
        // this was a fresh event or a redelivery.
        return response()->json(['received' => true]);
    }

    /**
     * Resolves the Shop row for tenant attribution on the stored event.
     * Returns null (never throws) for an unrecognized/malformed domain
     * or a domain with no matching Shop — the webhook is still stored
     * and acknowledged either way; a null shop is a valid, expected
     * state for e.g. a redelivered webhook for a shop that was later
     * hard-deleted, and must never block ingestion.
     */
    private function identifyShop(?string $rawDomain): ?Shop
    {
        try {
            $domain = $rawDomain ? $this->domainValidator->validateAndNormalize($rawDomain) : null;
        } catch (InvalidShopDomainException) {
            return null;
        }

        return $domain ? Shop::query()->where('shopify_domain', $domain)->first() : null;
    }

    /**
     * Idempotency check happens here, not in the job: `shopify_webhook_id`
     * (when present) is the primary key for "have we seen this exact
     * delivery before" — `firstOrCreate` makes a redelivery a no-op read
     * instead of a duplicate insert. `payload_hash` is stored regardless,
     * as the secondary duplicate-detection signal described in the
     * webhooks table migration, but does not itself gate re-processing
     * (Shopify's webhook ID is authoritative when present).
     *
     * @return array{0: Webhook, 1: bool} the event row, and whether it was newly created
     */
    private function storeEvent(string $topic, ?string $webhookId, array $payload, string $payloadHash, ?Shop $shop): array
    {
        $attributes = [
            'shop_id' => $shop?->id,
            'topic' => $topic,
            'payload' => $payload,
            'payload_hash' => $payloadHash,
            'status' => 'received',
        ];

        $log = $webhookId
            ? Webhook::query()->firstOrCreate(['shopify_webhook_id' => $webhookId], $attributes)
            : Webhook::query()->create($attributes);

        return [$log, $log->wasRecentlyCreated];
    }

    private function dispatchOrSettle(string $topic, Webhook $log): void
    {
        $jobClass = config("shopify.webhooks.{$topic}");

        if (! $jobClass) {
            // No handler registered for this topic — nothing to do, but
            // still a legitimately received (and now settled) event, not
            // an error.
            Log::info('Webhook received for a topic with no registered handler', ['webhook_id' => $log->id, 'topic' => $topic]);
            $log->markProcessed();

            return;
        }

        $jobClass::dispatch($log->id)->onQueue('webhooks');

        Log::info('Webhook queued for processing', ['webhook_id' => $log->id, 'topic' => $topic, 'job' => $jobClass]);
    }
}
