<?php

namespace App\Providers;

use App\Events\Customers\CustomerEnrolled;
use App\Events\Points\PointsPosted;
use App\Events\Referrals\ReferralCompleted;
use App\Events\Shop\ShopInstalled;
use App\Events\Shop\ShopUninstalled;
use App\Events\Subscription\SubscriptionActivated;
use App\Events\VipTiers\VipTierChanged;
use App\Listeners\Analytics\RecordCustomerJoinedAnalyticsEvent;
use App\Listeners\Analytics\RecordPointsAnalyticsEvent;
use App\Listeners\Analytics\RecordVipTierChangedAnalyticsEvent;
use App\Listeners\Customers\SendWelcomeNotification;
use App\Listeners\Notifications\SendPointsEarnedNotification;
use App\Listeners\Notifications\SendVipTierChangedNotification;
use App\Listeners\Points\AwardAccountCreationPoints;
use App\Listeners\Referrals\RewardReferrerOnCompletion;
use App\Listeners\Shop\ProvisionDefaultLoyaltyProgram;
use App\Listeners\Shop\PurgeShopDataOnUninstall;
use App\Listeners\Subscription\SyncPlanEntitlementsCache;
use App\Listeners\VipTiers\EvaluateVipTierOnPointsPosted;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        ShopInstalled::class => [
            ProvisionDefaultLoyaltyProgram::class,
        ],
        ShopUninstalled::class => [
            PurgeShopDataOnUninstall::class,
        ],
        SubscriptionActivated::class => [
            SyncPlanEntitlementsCache::class,
        ],

        CustomerEnrolled::class => [
            SendWelcomeNotification::class,
            AwardAccountCreationPoints::class,
            RecordCustomerJoinedAnalyticsEvent::class,
        ],
        PointsPosted::class => [
            EvaluateVipTierOnPointsPosted::class,
            RecordPointsAnalyticsEvent::class,
            SendPointsEarnedNotification::class,
        ],
        ReferralCompleted::class => [
            RewardReferrerOnCompletion::class,
        ],
        VipTierChanged::class => [
            RecordVipTierChangedAnalyticsEvent::class,
            SendVipTierChangedNotification::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
