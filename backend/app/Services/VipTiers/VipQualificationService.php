<?php

namespace App\Services\VipTiers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\VipTier;
use Illuminate\Support\Carbon;

/**
 * VIP: computes a customer's metric for ONE tier's qualification method
 * over ONE tier's evaluation period, and answers "does this customer
 * qualify for this tier." This is the extensibility point for future
 * qualification methods — see the METHOD_* match below and
 * docs/VIP_TIERS.md; adding one is a new `case` here plus a new
 * nullable threshold column on `vip_tiers`, never a change to
 * VipEvaluationService or VipTierService.
 *
 * TIMEZONE HANDLING: every period boundary (calendar year start,
 * rolling window start) is computed in the SHOP'S configured IANA
 * timezone (`shops.timezone`, populated from Shopify's shop info at
 * OAuth time — see docs/AUTHENTICATION.md), falling back to UTC only
 * if a shop somehow has none. Computing "start of this year" in server
 * time or naive UTC would silently shift a customer in/out of a tier
 * near midnight for shops outside UTC — exactly the class of bug this
 * task's explicit timezone-handling requirement exists to prevent.
 */
class VipQualificationService
{
    public function qualifies(Customer $customer, VipTier $tier): bool
    {
        return $this->metricFor($customer, $tier) >= $tier->threshold();
    }

    public function metricFor(Customer $customer, VipTier $tier): int
    {
        [$periodStart, $periodEnd] = $this->periodWindow($customer, $tier);

        return match ($tier->qualification_method) {
            VipTier::METHOD_TOTAL_SPEND => $this->totalSpend($customer, $periodStart, $periodEnd),
            VipTier::METHOD_ORDER_COUNT => $this->orderCount($customer, $periodStart, $periodEnd),
            default => $this->pointsEarned($customer, $periodStart, $periodEnd),
        };
    }

    /** A human-readable audit string for `customer_vip_history.qualification_reason` — e.g. "Reached $500.00 total spend (lifetime)". */
    public function describeQualification(Customer $customer, VipTier $tier): string
    {
        $metric = $this->metricFor($customer, $tier);

        $description = match ($tier->qualification_method) {
            VipTier::METHOD_TOTAL_SPEND => '$'.number_format($metric / 100, 2).' total spend',
            VipTier::METHOD_ORDER_COUNT => "{$metric} qualifying orders",
            default => "{$metric} points earned",
        };

        return "{$description} ({$tier->evaluation_period})";
    }

    /**
     * @return array{0: ?Carbon, 1: Carbon} [period start (null = lifetime, no lower bound), period end (always "now")]
     */
    private function periodWindow(Customer $customer, VipTier $tier): array
    {
        $tz = $this->timezoneFor($customer);
        $now = Carbon::now($tz);

        [$start, $end] = match ($tier->evaluation_period) {
            VipTier::PERIOD_CALENDAR_YEAR => [$now->copy()->startOfYear(), $now],
            VipTier::PERIOD_ROLLING => [$now->copy()->subDays($tier->rolling_period_days ?? 365), $now],
            default => [null, $now], // lifetime
        };

        // The boundaries above are computed using the SHOP'S wall-clock
        // time (so "start of year" means midnight Jan 1 in the shop's
        // own timezone, not UTC) — but `created_at` columns store naive
        // UTC datetimes, so both boundaries must be normalized to UTC
        // before being used in a query, or the comparison would be
        // silently offset by the shop's UTC difference.
        return [$start?->utc(), $end->utc()];
    }

    private function timezoneFor(Customer $customer): string
    {
        return $customer->shop->timezone ?: 'UTC';
    }

    private function pointsEarned(Customer $customer, ?Carbon $start, Carbon $end): int
    {
        if ($start === null) {
            return $customer->point?->lifetime_earned ?? 0;
        }

        return (int) PointTransaction::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->id)
            ->where('direction', PointTransaction::DIRECTION_EARN)
            ->whereBetween('created_at', [$start, $end])
            ->sum('points');
    }

    private function totalSpend(Customer $customer, ?Carbon $start, Carbon $end): int
    {
        return (int) Order::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->id)
            ->where('financial_status', 'paid')
            ->when($start, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->sum('total_cents');
    }

    private function orderCount(Customer $customer, ?Carbon $start, Carbon $end): int
    {
        return Order::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->id)
            ->where('financial_status', 'paid')
            ->when($start, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->count();
    }

    /**
     * "Progress to next tier" — the exact computation both
     * CustomerVipController (admin-facing) and StorefrontVipController
     * (customer-facing) need, extracted here so it exists in exactly
     * one place rather than being copy-pasted per controller. Finds the
     * next active, plan-available, date-windowed tier above the
     * customer's current one and reports how close they are to it in
     * that tier's own qualification method's terms.
     *
     * @return array{next_tier: ?VipTier, qualification_method?: string, current_progress?: int, required?: int, remaining?: int, percent_complete?: int}
     */
    public function progressToNextTier(Customer $customer): array
    {
        $nextTier = VipTier::query()
            ->where('shop_id', $customer->shop_id)
            ->where('is_active', true)
            ->where('sort_order', '>', $customer->vipTier?->sort_order ?? -1)
            ->orderBy('sort_order')
            ->get()
            ->first(fn (VipTier $tier) => $tier->isAvailableToShop() && $tier->isWithinDateWindow());

        if (! $nextTier) {
            return ['next_tier' => null];
        }

        $current = $this->metricFor($customer, $nextTier);
        $required = $nextTier->threshold();

        return [
            'next_tier' => $nextTier,
            'qualification_method' => $nextTier->qualification_method,
            'current_progress' => $current,
            'required' => $required,
            'remaining' => max(0, $required - $current),
            'percent_complete' => $required > 0 ? min(100, (int) round(($current / $required) * 100)) : 100,
        ];
    }
}
