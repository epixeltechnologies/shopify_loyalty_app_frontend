<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::night();

        $this->gate();
    }

    /**
     * The Horizon dashboard exposes queue internals (job payloads,
     * failure traces) — restricted to the 'owner' role only, since a
     * failed job's payload can include customer PII. See
     * docs/SECURITY.md for why this dashboard isn't left open in any
     * environment, including local (protects against accidentally
     * shipping a misconfigured `APP_ENV`).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user) => $user?->role?->slug === 'owner');
    }
}
