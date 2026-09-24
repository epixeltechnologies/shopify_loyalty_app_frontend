<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(1000, 50000); // cents

        return [
            'shop_id' => Shop::factory(),
            'shopify_order_id' => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'order_number' => (string) $this->faker->unique()->numberBetween(1000, 9999),
            'financial_status' => 'paid',
            'fulfillment_status' => null,
            'currency' => 'USD',
            'subtotal_cents' => $total,
            'total_cents' => $total,
            'total_discounts_cents' => 0,
            'total_tax_cents' => 0,
            'total_shipping_cents' => 0,
            'shopify_created_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['cancelled_at' => now(), 'cancel_reason' => 'customer']);
    }
}
