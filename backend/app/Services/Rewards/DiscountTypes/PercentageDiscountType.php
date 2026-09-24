<?php

namespace App\Services\Rewards\DiscountTypes;

use App\Models\Reward;

class PercentageDiscountType implements RewardDiscountTypeInterface
{
    public function supports(string $rewardType): bool
    {
        return $rewardType === Reward::TYPE_PERCENTAGE_DISCOUNT;
    }

    public function buildCustomerGets(Reward $reward): array
    {
        $percentage = (float) ($reward->value['percentage'] ?? 0);

        return [
            // Shopify's DiscountPercentage.value is a fraction (0.10 = 10%), not a whole number.
            'value' => ['percentage' => $percentage / 100],
            'items' => ['all' => true],
        ];
    }

    public function mutationName(): string
    {
        return 'discountCodeBasicCreate';
    }
}
