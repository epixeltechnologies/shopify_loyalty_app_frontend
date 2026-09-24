<?php

namespace App\Support\Cache;

/**
 * Centralized cache key + TTL registry. Every cache key used anywhere
 * in the app is built through one of these methods rather than an
 * inline string, for two reasons: (1) it's the only way to guarantee
 * `CacheService::flushShop()` (see below) actually knows every key
 * pattern that needs invalidating when a shop's underlying data
 * changes, and (2) it prevents key collisions from a typo (e.g.
 * "shop:1:plan" vs "shop_1_plan") that are otherwise invisible until
 * two unrelated things start reading each other's cached value.
 */
final class CacheKeys
{
    public static function shopActivePlan(int $shopId): string
    {
        return "shop:{$shopId}:active_plan";
    }

    public static function shopFeature(int $shopId, string $featureKey): string
    {
        return "shop:{$shopId}:feature:{$featureKey}";
    }

    public static function activePlans(): string
    {
        return 'plans:active';
    }

    /** Tag applied to every shop-scoped cache entry, so a shop's cache can be flushed as a unit. */
    public static function shopTag(int $shopId): string
    {
        return "shop:{$shopId}";
    }

    public const TTL_SHORT = 300;   // 5 minutes — entitlement/feature lookups (subscription state changes rarely, but should propagate fast)

    public const TTL_MEDIUM = 3600; // 1 hour — plan catalog (changes only on deploy/admin action)
}
