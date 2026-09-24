<?php

namespace App\Services\Cache;

use App\Support\Cache\CacheKeys;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Thin wrapper over Laravel's Cache facade that adds shop-scoped
 * tagging (supported by the `redis` driver — see config/cache.php).
 *
 * Invalidation strategy:
 *   - Read-through caching (`remember`) with short TTLs (see CacheKeys)
 *     as the primary defense — even an un-invalidated cache entry
 *     self-heals within minutes.
 *   - Explicit invalidation (`forgetShop`) is called from the services
 *     that mutate the underlying data (e.g.
 *     SyncPlanEntitlementsCache listener, on SubscriptionActivated) so
 *     the common case (a plan change) is reflected immediately rather
 *     than waiting out the TTL.
 *   - Every shop-scoped cache write is tagged with `CacheKeys::shopTag()`,
 *     so `forgetShop()` clears everything for that shop in one call
 *     without needing to enumerate every key pattern that shop might
 *     have touched.
 */
class CacheService
{
    public function remember(string $key, int $ttlSeconds, Closure $callback, ?int $shopId = null): mixed
    {
        if ($shopId !== null) {
            return Cache::tags([CacheKeys::shopTag($shopId)])->remember($key, $ttlSeconds, $callback);
        }

        return Cache::remember($key, $ttlSeconds, $callback);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    /** Flushes every cache entry tagged for this shop — call after any mutation to plan/subscription/feature state. */
    public function forgetShop(int $shopId): void
    {
        Cache::tags([CacheKeys::shopTag($shopId)])->flush();
    }
}
