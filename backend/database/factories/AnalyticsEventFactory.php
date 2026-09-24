<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticsEvent> */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'event_type' => $this->faker->randomElement([
                'customer.enrolled', 'points.earned', 'points.redeemed',
                'reward.redeemed', 'referral.completed', 'vip_tier.changed',
            ]),
            'properties' => [],
        ];
    }
}
