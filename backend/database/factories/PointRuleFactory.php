<?php

namespace Database\Factories;

use App\Models\PointRule;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PointRule> */
class PointRuleFactory extends Factory
{
    protected $model = PointRule::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => $this->faker->words(3, true),
            'type' => 'points_per_dollar',
            'config' => ['points_per_dollar' => 1],
            'status' => 'active',
        ];
    }

    public function signupBonus(): static
    {
        return $this->state(fn () => ['type' => 'signup_bonus', 'config' => ['bonus_points' => 500]]);
    }
}
