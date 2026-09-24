<?php

namespace App\Exceptions\Billing;

use App\Models\Shop;
use RuntimeException;

/** Thrown by SubscriptionAccessService::assertFullAccess() for a shop that has uninstalled the app. Mirrors EnsureShopIsActive middleware's 410 response for non-HTTP callers. */
class ShopInactiveException extends RuntimeException
{
    public function __construct(public readonly Shop $shop)
    {
        parent::__construct("Shop [{$shop->shopify_domain}] is no longer installed.");
    }
}
