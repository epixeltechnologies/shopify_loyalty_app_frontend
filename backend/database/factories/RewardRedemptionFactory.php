<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RewardRedemption> */
class RewardRedemptionFactory extends Factory
{
    protected $model = RewardRedemption::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'customer_id' => Customer::factory(),
            'reward_id' => Reward::factory(),
            'points_spent' => 500,
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'shopify_discount_id' => 'gid://shopify/DiscountCodeNode/'.$this->faker->numberBetween(100000, 999999),
            'shopify_discount_code' => Str::upper(Str::random(10)),
            'fulfilled_at' => now(),
        ]);
    }
}
