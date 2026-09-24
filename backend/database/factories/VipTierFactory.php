<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\VipTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VipTier> */
class VipTierFactory extends Factory
{
    protected $model = VipTier::class;

    public function definition(): array
    {
        $slug = $this->faker->randomElement(['silver', 'gold', 'platinum']);

        return [
            'shop_id' => Shop::factory(),
            'name' => ucfirst($slug),
            'slug' => $slug,
            'threshold_points' => match ($slug) {
                'silver' => 0,
                'gold' => 1000,
                'platinum' => 5000,
            },
            'sort_order' => match ($slug) {
                'silver' => 1, 'gold' => 2, 'platinum' => 3,
            },
            'perks' => ['points_multiplier' => 1.0],
            'is_active' => true,
        ];
    }
}
