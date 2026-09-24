<?php

namespace App\Services\Rewards\DiscountTypes;

use App\Models\Reward;

class FixedAmountDiscountType implements RewardDiscountTypeInterface
{
    public function supports(string $rewardType): bool
    {
        return $rewardType === Reward::TYPE_FIXED_DISCOUNT;
    }

    public function buildCustomerGets(Reward $reward): array
    {
        $amountCents = (int) ($reward->value['amount_cents'] ?? 0);

        return [
            'value' => [
                'discountAmount' => [
                    'amount' => number_format($amountCents / 100, 2, '.', ''),
                    'appliesOnEachItem' => false,
                ],
            ],
            'items' => ['all' => true],
        ];
    }

    public function mutationName(): string
    {
        return 'discountCodeBasicCreate';
    }
}
