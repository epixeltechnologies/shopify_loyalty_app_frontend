<?php

namespace App\Jobs\Webhooks;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Shop;
use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for every webhook-topic job. Centralizes the status
 * lifecycle (processing -> processed | failed), attempt counting, and
 * structured logging so each concrete job (HandleOrderCreatedJob, etc.)
 * only implements `process()` — the actual topic-specific work — and
 * never touches the `webhooks` table's bookkeeping directly.
 *
 * Constructed from a `Webhook` row's ID (not the raw payload/domain) so
 * every job can look up its own event record, the source of truth for
 * "has this already been processed" and "how many times has this been
 * attempted" — see docs/WEBHOOKS.md#retry-behavior.
 *
 * Retry semantics: `$tries`/`$backoff` (from HasDefaultRetryPolicy,
 * overridable per job) bound retries — there is no infinite retry.
 * `recordAttemptFailure()` runs on every failed attempt (for
 * observability mid-retry); `failed()` — Laravel's built-in hook,
 * called exactly once when retries are exhausted — is what marks the
 * event permanently `failed`, which is what surfaces it for admin
 * inspection/reprocessing (see docs/WEBHOOKS.md#failure-recovery).
 */
abstract class WebhookJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $webhookId) {}

    final public function handle(): void
    {
        $webhook = Webhook::query()->find($this->webhookId);

        if (! $webhook) {
            // The event row was deleted out from under us (shouldn't
            // normally happen — webhooks rows are never hard-deleted by
            // this app) — nothing to process, and nothing to mark.
            Log::warning('Webhook job ran for a missing event row', ['webhook_id' => $this->webhookId, 'job' => static::class]);

            return;
        }

        if ($webhook->isTerminal()) {
            // Already processed or permanently failed by a previous
            // attempt/redelivery — a safety net alongside the
            // controller-level idempotency check, in case a job is ever
            // manually re-dispatched for an already-settled event.
            Log::info('Webhook job skipped — event already in a terminal state', [
                'webhook_id' => $webhook->id, 'topic' => $webhook->topic, 'status' => $webhook->status,
            ]);

            return;
        }

        $webhook->markProcessing();
        Log::info('Webhook processing started', ['webhook_id' => $webhook->id, 'topic' => $webhook->topic, 'shop_id' => $webhook->shop_id]);

        $shop = $webhook->shop_id ? Shop::query()->find($webhook->shop_id) : null;

        try {
            $this->process($webhook, $shop);

            $webhook->markProcessed();
            Log::info('Webhook processing completed', ['webhook_id' => $webhook->id, 'topic' => $webhook->topic]);
        } catch (Throwable $e) {
            // Never log the payload itself — it may contain customer
            // PII (email, name, address) — only the exception message
            // and class.
            $webhook->recordAttemptFailure($e->getMessage());

            Log::warning('Webhook processing attempt failed', [
                'webhook_id' => $webhook->id,
                'topic' => $webhook->topic,
                'attempt' => $this->attempts(),
                'exception' => get_class($e),
            ]);

            throw $e; // let Laravel's queue retry/backoff take over
        }
    }

    /**
     * Called once, automatically, after `$tries` attempts have all
     * failed — this is the permanent, terminal failure, distinct from
     * `recordAttemptFailure()` which fires on every individual attempt.
     */
    public function failed(Throwable $e): void
    {
        $webhook = Webhook::query()->find($this->webhookId);
        $webhook?->markFailed($e->getMessage());

        Log::error('Webhook processing permanently failed — retries exhausted', [
            'webhook_id' => $this->webhookId,
            'job' => static::class,
            'exception' => get_class($e),
        ]);
    }

    /** Implemented by each concrete topic job. Throw to trigger a retry; never catch-and-swallow here. */
    abstract protected function process(Webhook $webhook, ?Shop $shop): void;
}
