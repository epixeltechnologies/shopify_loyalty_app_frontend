<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Webhook> */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        $payload = ['id' => $this->faker->numberBetween(1000, 9999)];

        return [
            'shop_id' => Shop::factory(),
            'topic' => $this->faker->randomElement(['orders/create', 'orders/updated', 'customers/update']),
            'shopify_webhook_id' => (string) $this->faker->unique()->numberBetween(1000000, 9999999),
            'payload' => $payload,
            'payload_hash' => hash('sha256', json_encode($payload)),
            'status' => 'received',
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => ['status' => 'processed', 'processed_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed', 'failed_at' => now(), 'error' => 'Simulated failure', 'attempts' => 5]);
    }
}
