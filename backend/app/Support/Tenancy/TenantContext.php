<?php

namespace App\Support\Tenancy;

use App\Models\Shop;
use RuntimeException;

/**
 * Request-scoped holder for the resolved shop. Populated by
 * App\Http\Middleware\ResolveShopFromSession (or ResolveShopFromApiToken)
 * early in the middleware stack, before any tenant-scoped query runs.
 */
final class TenantContext
{
    private static ?Shop $shop = null;

    public static function set(Shop $shop): void
    {
        self::$shop = $shop;
    }

    public static function hasShop(): bool
    {
        return self::$shop !== null;
    }

    public static function shop(): Shop
    {
        if (self::$shop === null) {
            throw new RuntimeException('No tenant (shop) has been resolved for this request.');
        }

        return self::$shop;
    }

    public static function shopId(): int
    {
        return self::shop()->getKey();
    }

    /** For queued jobs / console commands that need to run "as" a shop. */
    public static function runAs(Shop $shop, callable $callback): mixed
    {
        $previous = self::$shop;
        self::$shop = $shop;

        try {
            return $callback();
        } finally {
            self::$shop = $previous;
        }
    }

    public static function clear(): void
    {
        self::$shop = null;
    }
}
