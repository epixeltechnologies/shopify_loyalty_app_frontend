<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\SubscriptionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscriptionEvent> */
class SubscriptionEventFactory extends Factory
{
    protected $model = SubscriptionEvent::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'to_status' => 'active',
            'trigger' => 'shopify_webhook',
        ];
    }
}
