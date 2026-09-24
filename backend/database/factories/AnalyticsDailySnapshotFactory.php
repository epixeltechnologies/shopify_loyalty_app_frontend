<?php

namespace Database\Factories;

use App\Models\AnalyticsDailySnapshot;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticsDailySnapshot> */
class AnalyticsDailySnapshotFactory extends Factory
{
    protected $model = AnalyticsDailySnapshot::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'metric' => $this->faker->randomElement([
                'points_issued', 'points_redeemed', 'active_members', 'referrals_completed',
            ]),
            'value' => $this->faker->randomFloat(2, 0, 5000),
        ];
    }
}
