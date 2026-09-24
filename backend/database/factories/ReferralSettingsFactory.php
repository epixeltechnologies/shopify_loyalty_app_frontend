<?php

namespace Database\Factories;

use App\Models\ReferralSettings;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReferralSettings> */
class ReferralSettingsFactory extends Factory
{
    protected $model = ReferralSettings::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'enabled' => true,
            'referrer_reward_points' => 500,
            'referee_reward_points' => 250,
            'minimum_qualifying_order_cents' => null,
            'require_first_purchase' => true,
            'reward_delay_days' => 0,
            'attribution_window_days' => 30,
            'max_referrals_per_customer' => null,
        ];
    }
}
