<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true).' Plan',
            'description' => $this->faker->sentence(),
            'price_monthly_cents' => $this->faker->randomElement([2900, 9900]),
            'currency' => 'USD',
            'max_active_customers' => $this->faker->randomElement([500, 5000, null]),
            'max_active_point_rules' => $this->faker->randomElement([5, null]),
            'sort_order' => 0,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function starter(): static
    {
        return $this->state(fn () => [
            'slug' => 'starter', 'name' => 'Starter Plan',
            'max_active_customers' => 500, 'max_active_point_rules' => 5,
            'is_default' => true, 'sort_order' => 1,
        ]);
    }

    public function professional(): static
    {
        return $this->state(fn () => [
            'slug' => 'professional', 'name' => 'Professional Plan',
            'max_active_customers' => 5000, 'max_active_point_rules' => null,
            'sort_order' => 2,
        ]);
    }
}
