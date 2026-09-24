<?php

namespace App\Listeners\Analytics;

use App\Events\VipTiers\VipTierChanged;
use App\Services\Analytics\EventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Reads the direction VipTierService already computed correctly
 * (comparing tier `sort_order`, never raw IDs — see that service's
 * `resolveDirection()`) rather than re-deriving it here, avoiding a
 * second, potentially-drifting copy of the same logic.
 */
class RecordVipTierChangedAnalyticsEvent implements ShouldQueue
{
    public function __construct(private readonly EventRecorder $events) {}

    public function handle(VipTierChanged $event): void
    {
        if ($event->direction === 'initial') {
            return; // a customer's first-ever tier assignment isn't an "upgrade" or "downgrade" event
        }

        $this->events->record(
            $event->customer->shop,
            $event->direction === 'upgrade' ? 'vip.upgraded' : 'vip.downgraded',
            $event->customer,
            ['from_tier_id' => $event->previousTierId, 'to_tier_id' => $event->newTierId],
        );
    }
}
