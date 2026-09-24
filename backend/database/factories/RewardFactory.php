<?php

namespace Database\Factories;

use App\Models\Reward;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reward> */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => 'percentage_discount',
            'points_cost' => $this->faker->randomElement([100, 250, 500, 1000]),
            'value' => ['percentage' => $this->faker->randomElement([5, 10, 15, 20])],
            'status' => 'active',
        ];
    }

    public function percentageDiscount(int $percentage = 10): static
    {
        return $this->state(fn () => ['type' => 'percentage_discount', 'value' => ['percentage' => $percentage]]);
    }

    public function fixedDiscount(int $amountCents = 500): static
    {
        return $this->state(fn () => ['type' => 'fixed_discount', 'value' => ['amount_cents' => $amountCents]]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn () => ['type' => 'free_shipping', 'value' => []]);
    }
}
