<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Point> */
class PointFactory extends Factory
{
    protected $model = Point::class;

    public function definition(): array
    {
        $customer = Customer::factory()->create();

        return [
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_redeemed' => 0,
            'lifetime_expired' => 0,
        ];
    }
}
