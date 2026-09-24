<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $group = $this->faker->randomElement(['customers', 'points', 'rewards', 'settings', 'billing']);

        return [
            'name' => $this->faker->words(2, true),
            'slug' => $group.'.'.$this->faker->unique()->word(),
            'group' => $group,
            'description' => $this->faker->sentence(),
        ];
    }
}
