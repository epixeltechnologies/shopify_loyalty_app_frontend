<?php

namespace App\Services\Billing;

use App\Models\Shop;
use App\Models\SubscriptionUsage;
use Illuminate\Support\Facades\DB;

/**
 * Maintains fast-read usage counters used by EntitlementService so limit
 * checks avoid COUNT(*) on every request. Increment/decrement is called
 * from the relevant domain services (customer enrollment, point rule
 * creation) inside the same DB transaction as the write.
 */
class UsageTrackerService
{
    public function current(Shop $shop, string $metric): int
    {
        return SubscriptionUsage::query()
            ->where('shop_id', $shop->id)
            ->where('metric', $metric)
            ->value('value') ?? 0;
    }

    public function increment(Shop $shop, string $metric, int $by = 1): void
    {
        SubscriptionUsage::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'metric' => $metric],
            ['value' => 0]
        );

        DB::table('subscription_usage')
            ->where('shop_id', $shop->id)
            ->where('metric', $metric)
            ->increment('value', $by);
    }

    /**
     * Atomically checks-and-increments in one locked transaction —
     * this is what closes the check-then-act race a naive
     * "if (canAddCustomer()) { create(); increment(); }" sequence has:
     * two concurrent requests can both read "under limit" before either
     * writes, letting a shop exceed its plan limit under load.
     *
     * `lockForUpdate()` on the counter row means the second concurrent
     * caller for the same shop+metric blocks until the first commits,
     * then re-reads the now-current value — so only one of two
     * simultaneous "last slot" requests ever succeeds. Callers
     * (CustomerService::enroll(), PointRuleService::create()) call this
     * FIRST, inside their own transaction, before creating the row the
     * counter represents — see each service for why the ordering matters.
     *
     * $limit === null means unlimited: always increments, never checks.
     * Returns false without incrementing when the limit is already
     * reached — the caller is expected to throw
     * App\Exceptions\Billing\LimitReachedException in that case.
     */
    public function tryIncrementIfUnderLimit(Shop $shop, string $metric, ?int $limit): bool
    {
        if ($limit === null) {
            $this->increment($shop, $metric);

            return true;
        }

        return DB::transaction(function () use ($shop, $metric, $limit) {
            SubscriptionUsage::query()->firstOrCreate(
                ['shop_id' => $shop->id, 'metric' => $metric],
                ['value' => 0]
            );

            $current = DB::table('subscription_usage')
                ->where('shop_id', $shop->id)
                ->where('metric', $metric)
                ->lockForUpdate()
                ->value('value');

            if ($current >= $limit) {
                return false;
            }

            DB::table('subscription_usage')
                ->where('shop_id', $shop->id)
                ->where('metric', $metric)
                ->increment('value');

            return true;
        });
    }

    public function decrement(Shop $shop, string $metric, int $by = 1): void
    {
        DB::table('subscription_usage')
            ->where('shop_id', $shop->id)
            ->where('metric', $metric)
            ->where('value', '>=', $by)
            ->decrement('value', $by);
    }

    public function recalculate(Shop $shop, string $metric, int $freshValue): void
    {
        SubscriptionUsage::query()->updateOrCreate(
            ['shop_id' => $shop->id, 'metric' => $metric],
            ['value' => $freshValue, 'recalculated_at' => now()]
        );
    }
}
