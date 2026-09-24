<?php

namespace App\Services\ErrorTracking;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The DEFAULT ErrorTracker implementation — writes to Laravel's normal
 * logging system. This is what's active until a real provider (Sentry,
 * etc.) is configured; the application is fully functional and
 * observable via log files with this alone, matching "the foundation
 * works without requiring a third-party service to be configured"
 * (see bootstrap/app.php's original reportable() comment, which this
 * class formalizes into a swappable abstraction rather than an inline
 * Log:: call).
 */
class LogErrorTracker implements ErrorTracker
{
    public function captureException(Throwable $e, array $context = []): void
    {
        Log::error($e->getMessage(), array_merge([
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], $context));
    }

    public function captureMessage(string $message, string $level = 'error', array $context = []): void
    {
        Log::log($level, $message, $context);
    }
}
