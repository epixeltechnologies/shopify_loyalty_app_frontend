<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

/**
 * Registers TenantContext as a singleton-style static holder and ensures
 * it is cleared between requests when running under octane/swoole to
 * avoid tenant bleed across requests sharing a worker process.
 */
class TenantServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->bound('octane')) {
            $this->app['events']->listen(
                \Laravel\Octane\Events\RequestReceived::class,
                fn () => TenantContext::clear()
            );
        }
    }
}
