<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderLineItem> */
class OrderLineItemFactory extends Factory
{
    protected $model = OrderLineItem::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'shop_id' => $order->shop_id,
            'order_id' => $order->id,
            'shopify_product_id' => (string) $this->faker->numberBetween(1000, 9999),
            'shopify_variant_id' => (string) $this->faker->numberBetween(10000, 99999),
            'title' => $this->faker->words(3, true),
            'quantity' => $this->faker->numberBetween(1, 3),
            'price_cents' => $this->faker->numberBetween(500, 5000),
            'total_discount_cents' => 0,
        ];
    }
}
