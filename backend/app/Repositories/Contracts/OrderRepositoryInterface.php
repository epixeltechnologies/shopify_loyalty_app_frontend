<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\Shop;

interface OrderRepositoryInterface
{
    public function findByShopifyOrderId(Shop $shop, string $shopifyOrderId): ?Order;

    /**
     * Idempotent upsert keyed on (shop_id, shopify_order_id) — see
     * orders migration comment. Used for both a fresh `orders/create`
     * and a subsequent `orders/updated`/redelivery, so callers never
     * need to branch on "does this order already exist."
     */
    public function upsertByShopifyOrderId(Shop $shop, string $shopifyOrderId, array $attributes): Order;

    public function paginateForShop(Shop $shop, int $perPage = 25);
}
