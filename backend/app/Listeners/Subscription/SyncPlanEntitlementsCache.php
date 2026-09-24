<?php

namespace App\Listeners\Subscription;

use App\Events\Subscription\SubscriptionActivated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncPlanEntitlementsCache implements ShouldQueue
{
    public function handle(SubscriptionActivated $event): void
    {
        $event->subscription->shop->entitlements()->forgetCache();
    }
}
