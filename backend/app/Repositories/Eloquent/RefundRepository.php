<?php

namespace App\Repositories\Eloquent;

use App\Models\Refund;
use App\Models\Shop;
use App\Repositories\Contracts\RefundRepositoryInterface;

class RefundRepository implements RefundRepositoryInterface
{
    public function findByShopifyRefundId(Shop $shop, string $shopifyRefundId): ?Refund
    {
        return Refund::query()
            ->where('shop_id', $shop->id)
            ->where('shopify_refund_id', $shopifyRefundId)
            ->first();
    }

    public function upsertByShopifyRefundId(Shop $shop, string $shopifyRefundId, array $attributes): Refund
    {
        return Refund::query()->updateOrCreate(
            ['shop_id' => $shop->id, 'shopify_refund_id' => $shopifyRefundId],
            $attributes,
        );
    }
}
