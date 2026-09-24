<?php

namespace App\Jobs\Concerns;

/**
 * Baseline retry/backoff policy for internally-scheduled jobs (analytics
 * rollups, points expiry) — 3 attempts, exponential-ish backoff. Webhook
 * jobs (app/Jobs/Webhooks/*) define their own, more generous policy
 * (5 tries, longer backoff) since Shopify's redelivery window and the
 * cost of missing a webhook differ from an internal cron-triggered job.
 */
trait HasDefaultRetryPolicy
{
    public int $tries = 3;

    public array $backoff = [10, 30, 90];
}
