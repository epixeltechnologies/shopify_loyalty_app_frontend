<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\VipSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VipSettings> */
class VipSettingsFactory extends Factory
{
    protected $model = VipSettings::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'enabled' => true,
            'notify_on_upgrade' => true,
            'notify_on_downgrade' => true,
        ];
    }
}
