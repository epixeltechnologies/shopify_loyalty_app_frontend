<?php

namespace App\Exceptions\Billing;

use App\Models\Shop;
use RuntimeException;

/** Thrown by SubscriptionAccessService::assertFullAccess() for a shop with no active/trialing plan. Mirrors RequireActiveSubscription middleware's 402 response for non-HTTP callers. */
class SubscriptionRequiredException extends RuntimeException
{
    public function __construct(public readonly Shop $shop)
    {
        parent::__construct("Shop [{$shop->shopify_domain}] has no active subscription.");
    }
}
