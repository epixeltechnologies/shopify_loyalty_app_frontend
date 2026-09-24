<?php

namespace App\Services\Rewards\DiscountTypes;

use App\Models\Reward;

/**
 * Free shipping is created through a different top-level Shopify
 * mutation entirely (`discountCodeFreeShippingCreate`, not
 * `discountCodeBasicCreate`) — it has no `customerGets` fragment at
 * all (there's no product/value to discount, just shipping rates), so
 * `buildCustomerGets()` returns an empty array; `ShopifyDiscountService`
 * branches on `mutationName()` to build the correct input shape rather
 * than ever needing this type to conform to `customerGets`'s shape.
 */
class FreeShippingDiscountType implements RewardDiscountTypeInterface
{
    public function supports(string $rewardType): bool
    {
        return $rewardType === Reward::TYPE_FREE_SHIPPING;
    }

    public function buildCustomerGets(Reward $reward): array
    {
        return [];
    }

    public function mutationName(): string
    {
        return 'discountCodeFreeShippingCreate';
    }
}
