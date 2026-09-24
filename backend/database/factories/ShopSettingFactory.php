<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShopSetting> */
class ShopSettingFactory extends Factory
{
    protected $model = ShopSetting::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'widget_enabled' => true,
            'widget_primary_color' => $this->faker->hexColor(),
            'points_expiry_days' => $this->faker->randomElement([null, 180, 365]),
            'notification_email' => $this->faker->companyEmail(),
            'points_earning_label' => 'points',
        ];
    }
}
