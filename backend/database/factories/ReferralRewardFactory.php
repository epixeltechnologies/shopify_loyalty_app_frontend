<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReferralReward> */
class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'referral_id' => Referral::factory(),
            'customer_id' => Customer::factory(),
            'beneficiary' => 'referrer',
            'reward_type' => 'points',
            'points_awarded' => 500,
            'granted_at' => now(),
        ];
    }
}
