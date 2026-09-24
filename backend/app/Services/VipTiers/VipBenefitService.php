<?php

namespace App\Services\VipTiers;

use App\Models\Customer;

/**
 * VIP: reads a customer's CURRENT tier's `perks` JSON — the flexible
 * benefits system the task asks for. Deliberately no enum/whitelist of
 * benefit types anywhere in this class or the schema: `perks` is a
 * free-form key-value bag, and adding a new benefit type is a matter
 * of the merchant setting a new key in a tier's config and a caller
 * reading it via `get()` — no code or migration change required.
 *
 * A handful of NAMED convenience methods exist only for benefits that
 * OTHER parts of this app need to read generically regardless of key
 * name (points_multiplier is consumed by PointsAccrualService) — these
 * are conveniences over `get()`, not a parallel enumeration of "the
 * benefit types this app supports."
 */
class VipBenefitService
{
    /** Generic accessor — the actual extensibility point. Any key a merchant puts in a tier's `perks` config is readable this way, known to this class or not. */
    public function get(Customer $customer, string $key, mixed $default = null): mixed
    {
        return $customer->vipTier?->perks[$key] ?? $default;
    }

    public function all(Customer $customer): array
    {
        return $customer->vipTier?->perks ?? [];
    }

    /** Consumed by PointsAccrualService — see docs/VIP_TIERS.md's points-integration section. Defaults to 1.0 (no multiplier) for a customer with no tier or a tier with no configured multiplier. */
    public function pointsMultiplierFor(Customer $customer): float
    {
        return (float) $this->get($customer, 'points_multiplier', 1.0);
    }

    public function hasFreeShipping(Customer $customer): bool
    {
        return (bool) $this->get($customer, 'free_shipping', false);
    }

    public function hasEarlyAccess(Customer $customer): bool
    {
        return (bool) $this->get($customer, 'early_access', false);
    }
}
