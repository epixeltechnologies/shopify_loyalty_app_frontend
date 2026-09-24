<?php

namespace App\Services\Analytics;

use App\Models\Shop;
use Illuminate\Support\Carbon;

/**
 * ANALYTICS: the ONE place every date-filter preset (Today, Yesterday,
 * Last 7/30/90 days, This month, Last month, This year, custom range)
 * is resolved into a concrete `[start, end]` window — shared by the
 * dashboard, every report type, and CSV export, so "what does 'this
 * month' mean" is answered identically everywhere rather than
 * reimplemented per caller.
 *
 * TIMEZONE HANDLING: every preset is computed using the SHOP'S wall-
 * clock timezone (`shops.timezone`), then normalized to UTC before
 * being returned — the same lesson (and the same bug class) as
 * VipQualificationService's period-window computation: `created_at`
 * columns store naive UTC datetimes, so "start of this month" must
 * mean midnight in the shop's own timezone, converted to the
 * corresponding UTC instant, not midnight UTC. See docs/ANALYTICS.md.
 */
class DateRangeResolver
{
    public const PRESETS = ['today', 'yesterday', 'last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month', 'this_year', 'custom'];

    /**
     * @return array{0: Carbon, 1: Carbon} [start, end] — both UTC, end is always the last instant of its day (23:59:59.999999)
     */
    public function resolve(Shop $shop, string $preset, ?string $customFrom = null, ?string $customTo = null): array
    {
        $tz = $shop->timezone ?: 'UTC';
        $now = Carbon::now($tz);

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => $this->resolveCustom($tz, $customFrom, $customTo),
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        return [$start->utc(), $end->utc()];
    }

    private function resolveCustom(string $tz, ?string $from, ?string $to): array
    {
        if (! $from || ! $to) {
            throw new \InvalidArgumentException('A custom date range requires both "from" and "to".');
        }

        return [Carbon::parse($from, $tz)->startOfDay(), Carbon::parse($to, $tz)->endOfDay()];
    }
}
