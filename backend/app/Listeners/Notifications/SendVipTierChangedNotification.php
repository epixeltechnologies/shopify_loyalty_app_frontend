<?php

namespace App\Listeners\Notifications;

use App\Events\VipTiers\VipTierChanged;
use App\Models\VipSettings;
use App\Models\VipTier;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendVipTierChangedNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(VipTierChanged $event): void
    {
        if ($event->direction === 'initial' || ! $event->newTierId) {
            return; // a first-ever assignment isn't an "upgrade"/"downgrade" notification
        }

        $shop = $event->customer->shop;
        $settings = VipSettings::query()->where('shop_id', $shop->id)->first();
        $isUpgrade = $event->direction === 'upgrade';

        if ($settings && (($isUpgrade && ! $settings->notify_on_upgrade) || (! $isUpgrade && ! $settings->notify_on_downgrade))) {
            return;
        }

        $tierName = VipTier::query()->find($event->newTierId)?->name ?? '';

        $this->notifications->notify($shop, $event->customer, $isUpgrade ? 'vip_upgraded' : 'vip_downgraded', [
            'tier_name' => $tierName,
        ]);
    }
}
