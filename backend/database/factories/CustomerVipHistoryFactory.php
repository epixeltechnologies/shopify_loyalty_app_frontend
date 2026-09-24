<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerVipHistory;
use App\Models\Shop;
use App\Models\VipTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerVipHistory> */
class CustomerVipHistoryFactory extends Factory
{
    protected $model = CustomerVipHistory::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'customer_id' => Customer::factory(),
            'to_vip_tier_id' => VipTier::factory(),
            'direction' => 'initial',
            'lifetime_points_at_change' => $this->faker->numberBetween(0, 5000),
        ];
    }
}
