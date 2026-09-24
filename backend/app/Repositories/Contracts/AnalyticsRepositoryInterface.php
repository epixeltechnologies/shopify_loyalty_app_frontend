<?php

namespace App\Repositories\Contracts;

use App\Models\Shop;
use Illuminate\Support\Collection;

interface AnalyticsRepositoryInterface
{
    /** @return Collection<int, \App\Models\AnalyticsDailySnapshot> */
    public function metricSeries(Shop $shop, string $metric, \DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function upsertSnapshot(Shop $shop, \DateTimeInterface $date, string $metric, float $value): void;
}
