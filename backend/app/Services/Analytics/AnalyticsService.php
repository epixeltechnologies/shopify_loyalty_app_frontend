<?php

namespace App\Services\Analytics;

use App\Models\Shop;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;

/**
 * ANALYTICS: reads pre-aggregated `analytics_daily_snapshots` — the read
 * path. Basic vs. advanced analytics is a plan feature
 * (analytics.basic / analytics.advanced), enforced at the route level
 * via `feature:analytics.advanced` — this service itself is not aware
 * of plans; it just serves the requested metric series.
 */
class AnalyticsService
{
    public function __construct(private readonly AnalyticsRepositoryInterface $analytics) {}

    public function series(Shop $shop, string $metric, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        return $this->analytics->metricSeries($shop, $metric, $from, $to);
    }

    /**
     * Advanced-tier metrics (cohort retention, redemption rate trends)
     * are computed on top of the same snapshot table — left for the
     * loyalty-engine milestone. See docs/NEXT_STEPS.md.
     */
    public function cohortRetention(Shop $shop, \DateTimeInterface $cohortMonth): array
    {
        return [];
    }
}
