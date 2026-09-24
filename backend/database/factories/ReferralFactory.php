<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Referral;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Referral> */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'referrer_customer_id' => Customer::factory(),
            'referral_code' => Str::upper(Str::random(8)),
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'referred_customer_id' => Customer::factory(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function clicked(): static
    {
        return $this->state(fn () => [
            'visitor_token' => Str::random(32),
            'status' => 'clicked',
            'clicked_at' => now(),
            'attribution_expires_at' => now()->addDays(30),
        ]);
    }

    public function registered(): static
    {
        return $this->state(fn () => [
            'referred_customer_id' => Customer::factory(),
            'visitor_token' => Str::random(32),
            'status' => 'registered',
            'clicked_at' => now()->subMinute(),
            'registered_at' => now(),
            'attribution_expires_at' => now()->addDays(30),
        ]);
    }

    public function qualified(): static
    {
        return $this->state(fn () => [
            'referred_customer_id' => Customer::factory(),
            'status' => 'qualified',
            'clicked_at' => now()->subDays(2),
            'registered_at' => now()->subDay(),
            'qualified_at' => now(),
            'qualifying_order_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'qualifying_order_value_cents' => 5000,
            'reward_scheduled_at' => now(),
            'attribution_expires_at' => now()->addDays(30),
        ]);
    }
}
