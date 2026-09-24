<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Refund> */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'shop_id' => $order->shop_id,
            'order_id' => $order->id,
            'shopify_refund_id' => (string) $this->faker->unique()->numberBetween(1000, 9999),
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
            'is_partial' => false,
            'shopify_created_at' => now(),
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (array $attrs) => [
            'amount_cents' => (int) round(($attrs['amount_cents'] ?? 1000) / 2),
            'is_partial' => true,
        ]);
    }
}
