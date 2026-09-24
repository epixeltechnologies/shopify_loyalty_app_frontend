<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\SubscriptionUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscriptionUsage> */
class SubscriptionUsageFactory extends Factory
{
    protected $model = SubscriptionUsage::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'metric' => SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS,
            'value' => $this->faker->numberBetween(0, 500),
            'recalculated_at' => now(),
        ];
    }
}
