<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\ShopInactiveException;
use App\Exceptions\Billing\SubscriptionRequiredException;
use App\Models\Shop;

/**
 * BILLING: the single source of truth for "is this shop allowed to use
 * the app right now at all" — the two-part gate every protected request
 * passes through (see EnsureShopIsActive + RequireActiveSubscription
 * middleware, both of which delegate their boolean checks here) and the
 * programmatic equivalent for non-HTTP callers (a scheduled job,
 * a console command) via assertFullAccess().
 *
 * Deliberately kept as two separate checks (shop active, then
 * subscription active) rather than one combined boolean — a shop that
 * uninstalled and a shop that simply has no paid plan are different
 * failure modes with different HTTP semantics (410 vs. 402) and
 * different remediation ("reinstall the app" vs. "pick a plan"); a
 * caller needing to distinguish them (as the middleware do, for
 * correct status codes) needs both checks exposed independently, not
 * folded into one opaque "denied" result.
 */
class SubscriptionAccessService
{
    public function isShopActive(Shop $shop): bool
    {
        return $shop->is_installed;
    }

    public function hasActiveSubscription(Shop $shop): bool
    {
        return $shop->entitlements()->hasActiveSubscription();
    }

    public function hasFullAccess(Shop $shop): bool
    {
        return $this->isShopActive($shop) && $this->hasActiveSubscription($shop);
    }

    /**
     * @throws ShopInactiveException|SubscriptionRequiredException
     */
    public function assertFullAccess(Shop $shop): void
    {
        if (! $this->isShopActive($shop)) {
            throw new ShopInactiveException($shop);
        }

        if (! $this->hasActiveSubscription($shop)) {
            throw new SubscriptionRequiredException($shop);
        }
    }
}
