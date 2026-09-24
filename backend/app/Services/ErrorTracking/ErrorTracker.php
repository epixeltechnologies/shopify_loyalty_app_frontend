<?php

namespace App\Services\ErrorTracking;

use Throwable;

/**
 * OPS: the single seam between this app's exception handling
 * (bootstrap/app.php's `reportable()` hook) and whatever error-tracking
 * PROVIDER a production deployment configures — Sentry, Bugsnag, Flare,
 * or none at all. No business code anywhere calls Sentry/Bugsnag SDKs
 * directly; everything goes through this interface, so adding or
 * swapping a provider later means writing one new implementation class
 * and changing one binding (see ErrorTrackingServiceProvider), never
 * touching application code. See docs/MONITORING.md#error-tracking.
 */
interface ErrorTracker
{
    /**
     * @param  array<string, mixed>  $context  Safe, non-sensitive context only —
     *                                          see docs/MONITORING.md's explicit allow/deny list.
     *                                          Never pass tokens, secrets, or raw customer PII here.
     */
    public function captureException(Throwable $e, array $context = []): void;

    /** For a notable event that isn't itself an exception (e.g. "webhook queue backlog exceeded threshold") but still warrants tracking. */
    public function captureMessage(string $message, string $level = 'error', array $context = []): void;
}
