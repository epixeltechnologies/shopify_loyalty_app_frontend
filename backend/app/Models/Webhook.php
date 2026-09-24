<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SYSTEM: inbound Shopify webhook log — the durable, idempotent event
 * record the whole webhook pipeline is built around. Not tenant-scoped
 * via BelongsToShop (shop_id is nullable and a shop may not be
 * resolvable when a row is first written, e.g. an unknown domain) — see
 * the migration comments for the two independent duplicate-detection
 * signals (`shopify_webhook_id` unique constraint, `payload_hash` index)
 * and what `failed_at` adds alongside `processed_at`.
 *
 * Status lifecycle: received -> processing -> processed | failed.
 * Transitions are made through the methods below (never a bare
 * `->update(['status' => ...])` scattered across job classes) so every
 * transition consistently stamps the right timestamp — see
 * App\Jobs\Webhooks\WebhookJob, the only caller of these in practice.
 */
class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id', 'topic', 'shopify_webhook_id', 'payload', 'payload_hash',
        'status', 'error', 'attempts', 'processed_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markProcessed(): void
    {
        $this->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
    }

    /** Called on every failed attempt, not just the final one — see WebhookJob::handle(). */
    public function recordAttemptFailure(string $error): void
    {
        $this->increment('attempts');
        $this->update(['error' => $error]);
    }

    /** Called once, when retries are exhausted (Laravel's job `failed()` hook) — permanent, not just this attempt. */
    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'failed_at' => now(), 'error' => $error]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['processed', 'failed'], true);
    }
}
