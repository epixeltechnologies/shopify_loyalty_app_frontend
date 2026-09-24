<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'shopify_customer_id' => (string) $this->faker->unique()->numberBetween(1000000, 9999999),
            'email' => $this->faker->unique()->safeEmail(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'referral_code' => Str::upper(Str::random(8)),
            'status' => 'active',
            'enrolled_at' => now(),
        ];
    }

    /** Also creates the 1:1 `points` row, mirroring what CustomerService::enroll() does. */
    public function configure(): static
    {
        return $this->afterCreating(function (Customer $customer) {
            $customer->point()->create(['shop_id' => $customer->shop_id, 'customer_id' => $customer->id]);
        });
    }
}
