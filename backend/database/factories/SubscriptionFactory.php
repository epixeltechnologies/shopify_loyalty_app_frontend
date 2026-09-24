<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'plan_id' => Plan::factory(),
            'shopify_subscription_id' => 'gid://shopify/AppSubscription/'.$this->faker->unique()->numberBetween(100000, 999999),
            'status' => 'active',
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn () => ['status' => 'trialing', 'trial_ends_at' => now()->addDays(14)]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
