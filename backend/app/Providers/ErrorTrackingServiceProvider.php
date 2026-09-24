<?php

namespace App\Providers;

use App\Services\ErrorTracking\ErrorTracker;
use App\Services\ErrorTracking\LogErrorTracker;
use Illuminate\Support\ServiceProvider;

/**
 * OPS: the ONE place a real error-tracking provider gets wired in.
 * Today this always binds LogErrorTracker (see that class's docblock).
 * To activate Sentry (or another provider) in production:
 *   1. `composer require sentry/sentry-laravel` (NOT done here — this
 *      app has no such dependency installed; adding it speculatively
 *      would violate "do not add unnecessary dependencies").
 *   2. Write a `SentryErrorTracker implements ErrorTracker` class (see
 *      docs/MONITORING.md#error-tracking for the exact implementation).
 *   3. Change the binding below to resolve it conditionally on
 *      `config('services.error_tracking.driver')` (already read from
 *      the existing `ERROR_TRACKING_DRIVER` env var — see .env.example).
 * No other file in this application needs to change.
 */
class ErrorTrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ErrorTracker::class, LogErrorTracker::class);
    }
}
