<?php

namespace App\Observers;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;

/**
 * Closes a real gap that existed before this observer: `PlanRepository::all()`
 * caches the plan catalog for an hour (`CacheKeys::TTL_MEDIUM`), and
 * `EntitlementService` caches each shop's resolved plan/feature lookups
 * for 5 minutes — both correctly invalidate on a SUBSCRIPTION change
 * (`SyncPlanEntitlementsCache` listener), but nothing previously
 * invalidated them when the PLAN CONFIGURATION itself changed (e.g. an
 * operator edits `plans.max_active_customers` directly, or re-runs
 * `PlanSeeder` with new limits). Without this, such a change could
 * silently serve stale limits/features for up to an hour.
 *
 * Registered for Plan, Feature, and PlanFeature (see
 * AppServiceProvider::boot()) — a change to any of the three means the
 * effective entitlements for every shop on the affected plan(s) may
 * have changed.
 */
class PlanConfigurationObserver
{
    public function saved(Plan|Feature|PlanFeature $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Plan|Feature|PlanFeature $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Plan|Feature|PlanFeature $model): void
    {
        Cache::forget(CacheKeys::activePlans());

        $planIds = match (true) {
            $model instanceof Plan => [$model->id],
            $model instanceof PlanFeature => [$model->plan_id],
            $model instanceof Feature => PlanFeature::query()->where('feature_id', $model->id)->pluck('plan_id')->all(),
        };

        if (empty($planIds)) {
            return;
        }

        // Only touches shops actually subscribed to an affected plan —
        // this is an infrequent, operator-triggered event, not a hot
        // request path, so a bounded query here is an acceptable cost
        // for correctness.
        Subscription::query()
            ->whereIn('plan_id', $planIds)
            ->whereIn('status', Subscription::ACTIVE_STATUSES)
            ->pluck('shop_id')
            ->unique()
            ->each(fn (int $shopId) => Cache::tags([CacheKeys::shopTag($shopId)])->flush());
    }
}
