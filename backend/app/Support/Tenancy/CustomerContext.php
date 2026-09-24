<?php

namespace App\Support\Tenancy;

use App\Models\Customer;
use RuntimeException;

/**
 * Request-scoped holder for the authenticated STOREFRONT customer —
 * the customer-facing counterpart to TenantContext. Populated by
 * App\Http\Middleware\ResolveStorefrontCustomer after
 * VerifyShopifyAppProxyRequest has already bound TenantContext.
 *
 * Every storefront controller reads the current customer from HERE,
 * never from a route parameter or a request input — this is what makes
 * "never trust a customer ID supplied blindly by the frontend" actually
 * true: there is no code path where a storefront request can specify
 * which customer it wants to act as. It always acts as whichever
 * customer Shopify's own signed `logged_in_customer_id` identified.
 */
final class CustomerContext
{
    private static ?Customer $customer = null;

    public static function set(Customer $customer): void
    {
        self::$customer = $customer;
    }

    public static function hasCustomer(): bool
    {
        return self::$customer !== null;
    }

    public static function customer(): Customer
    {
        if (self::$customer === null) {
            throw new RuntimeException('No storefront customer has been resolved for this request.');
        }

        return self::$customer;
    }

    public static function clear(): void
    {
        self::$customer = null;
    }
}
