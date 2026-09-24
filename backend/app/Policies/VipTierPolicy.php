<?php

namespace App\Policies;

use App\Models\VipTier;
use App\Support\Tenancy\TenantContext;

/**
 * Gates VIP tier *creation* by the shop's plan features (vip_tier.silver
 * / .gold / .platinum) rather than by tier count — a Starter shop may
 * only ever create its Silver/Gold tiers, never Platinum, and since a
 * shop can have at most one tier per slug (`unique(shop_id, slug)`),
 * this already IS the "max tier count" plan restriction without a
 * separate numeric limit — see docs/VIP_TIERS.md.
 */
class VipTierPolicy
{
    public function create(mixed $user, ?string $slug = null): bool
    {
        $shop = TenantContext::shop();
        $slug ??= request()->input('slug');

        return $shop->entitlements()->has("vip_tier.{$slug}");
    }

    public function update(mixed $user, VipTier $tier): bool
    {
        return TenantContext::hasShop() && $tier->shop_id === TenantContext::shopId();
    }

    public function view(mixed $user, VipTier $tier): bool
    {
        return TenantContext::hasShop() && $tier->shop_id === TenantContext::shopId();
    }
}
