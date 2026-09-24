<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Shop> */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        $handle = Str::slug($this->faker->unique()->company);

        return [
            'uuid' => (string) Str::uuid(),
            'shopify_domain' => "{$handle}.myshopify.com",
            'shopify_id' => (string) $this->faker->unique()->numberBetween(1000000, 9999999),
            'name' => $this->faker->company(),
            'email' => $this->faker->companyEmail(),
            'owner_name' => $this->faker->name(),
            'country_code' => $this->faker->countryCode(),
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'access_token' => Str::random(40),
            'scopes' => 'read_customers,write_customers,read_orders',
            'is_installed' => true,
            'installed_at' => now(),
            'onboarding_completed' => false,
        ];
    }

    public function uninstalled(): static
    {
        return $this->state(fn () => [
            'is_installed' => false,
            'uninstalled_at' => now(),
            'access_token' => null,
        ]);
    }
}
