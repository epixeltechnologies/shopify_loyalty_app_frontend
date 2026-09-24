<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->randomElement(['owner', 'manager', 'viewer']);

        return [
            'name' => ucfirst($slug),
            'slug' => $slug,
            'description' => $this->faker->sentence(),
        ];
    }
}
