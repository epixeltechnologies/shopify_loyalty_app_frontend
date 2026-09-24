<?php

namespace App\Jobs\Analytics;

use App\Jobs\Concerns\HasDefaultRetryPolicy;
use App\Models\Shop;
use App\Services\Analytics\SnapshotBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched once per shop, once per day (see routes/console.php
 * schedule), to roll up the previous day's activity into
 * analytics_daily_snapshots via SnapshotBuilder.
 */
class BuildDailyAnalyticsSnapshotsJob implements ShouldQueue
{
    use Dispatchable, HasDefaultRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Shop $shop, public readonly string $date) {}

    public function handle(SnapshotBuilder $builder): void
    {
        $builder->buildForShop($this->shop, \Illuminate\Support\Carbon::parse($this->date));
    }
}
