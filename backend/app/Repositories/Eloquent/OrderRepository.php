<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\Shop;
use App\Repositories\Contracts\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{
    public function findByShopifyOrderId(Shop $shop, string $shopifyOrderId): ?Order
    {
        return Order::query()
            ->where('shop_id', $shop->id)
            ->where('shopify_order_id', $shopifyOrderId)
            ->first();
    }

    public function upsertByShopifyOrderId(Shop $shop, string $shopifyOrderId, array $attributes): Order
    {
        return Order::query()->updateOrCreate(
            ['shop_id' => $shop->id, 'shopify_order_id' => $shopifyOrderId],
            $attributes,
        );
    }

    public function paginateForShop(Shop $shop, int $perPage = 25)
    {
        return Order::query()
            ->where('shop_id', $shop->id)
            ->latest('shopify_created_at')
            ->paginate($perPage);
    }
}
