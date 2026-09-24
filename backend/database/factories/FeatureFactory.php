<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Feature> */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        $group = $this->faker->randomElement(['vip_tiers', 'analytics', 'reporting', 'notifications', 'api']);

        return [
            'key' => $group.'.'.$this->faker->unique()->word(),
            'name' => $this->faker->words(3, true),
            'group' => $group,
            'type' => 'boolean',
            'description' => $this->faker->sentence(),
        ];
    }
}
