<?php

namespace Database\Factories;

use App\Models\AnalyticsExport;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticsExport> */
class AnalyticsExportFactory extends Factory
{
    protected $model = AnalyticsExport::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'report_type' => 'points',
            'status' => 'pending',
            'filters' => ['preset' => 'last_30_days'],
        ];
    }
}
