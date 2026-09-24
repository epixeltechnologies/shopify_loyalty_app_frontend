<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PointTransaction> */
class PointTransactionFactory extends Factory
{
    protected $model = PointTransaction::class;

    public function definition(): array
    {
        $points = $this->faker->numberBetween(10, 500);

        return [
            'shop_id' => Shop::factory(),
            'customer_id' => Customer::factory(),
            'direction' => PointTransaction::DIRECTION_EARN,
            'points' => $points,
            'balance_after' => $points,
            'source' => 'order',
        ];
    }
}
