<?php

namespace App\Repositories\Contracts;

use App\Models\Refund;
use App\Models\Shop;

interface RefundRepositoryInterface
{
    public function findByShopifyRefundId(Shop $shop, string $shopifyRefundId): ?Refund;

    public function upsertByShopifyRefundId(Shop $shop, string $shopifyRefundId, array $attributes): Refund;
}
