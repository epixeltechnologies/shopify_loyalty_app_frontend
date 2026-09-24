<?php

namespace App\Repositories\Eloquent;

use App\Models\AnalyticsDailySnapshot;
use App\Models\Shop;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use Illuminate\Support\Collection;

class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function metricSeries(Shop $shop, string $metric, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return AnalyticsDailySnapshot::query()
            ->where('shop_id', $shop->id)
            ->where('metric', $metric)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();
    }

    public function upsertSnapshot(Shop $shop, \DateTimeInterface $date, string $metric, float $value): void
    {
        AnalyticsDailySnapshot::query()->updateOrCreate(
            ['shop_id' => $shop->id, 'date' => $date->format('Y-m-d'), 'metric' => $metric],
            ['value' => $value]
        );
    }
}
