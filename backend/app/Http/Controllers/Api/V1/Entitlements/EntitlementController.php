<?php

namespace App\Http\Controllers\Api\V1\Entitlements;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Shop;
use App\Models\SubscriptionUsage;
use App\Services\Billing\UsageTrackerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * The one endpoint the frontend needs to render feature-gated UI
 * generically — see docs/ENTITLEMENTS.md#frontend-feature-gating and
 * frontend/src/hooks/useEntitlements.ts. Returns every known feature
 * flag (not just the ones the shop's plan happens to include) so the
 * frontend can render a locked state with an accurate "requires X plan"
 * message for anything unavailable, and both metered limits with
 * current usage.
 *
 * This is a read-only, UX-convenience endpoint — it does NOT replace
 * server-side enforcement. Every mutating endpoint independently
 * enforces its own entitlement check (EnsureFeatureEntitlement
 * middleware, policies, or a service throwing LimitReachedException)
 * regardless of what this endpoint returns; a stale or manipulated
 * frontend response can make the UI look wrong, never grant real access.
 */
class EntitlementController extends Controller
{
    public function index(): JsonResponse
    {
        $shop = TenantContext::shop();
        $entitlements = $shop->entitlements();

        $features = Feature::query()->get(['key', 'name', 'group'])->map(fn (Feature $feature) => [
            'key' => $feature->key,
            'name' => $feature->name,
            'group' => $feature->group,
            'available' => $entitlements->has($feature->key),
        ]);

        return response()->json(['data' => [
            'plan' => $entitlements->plan()?->slug,
            'features' => $features,
            'limits' => [
                'active_customers' => $this->limitSummary($entitlements->limit('max_active_customers'), $shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS),
                'active_reward_campaigns' => $this->limitSummary($entitlements->limit('max_active_point_rules'), $shop, SubscriptionUsage::METRIC_ACTIVE_POINT_RULES),
                // The Reward-catalog limit (see the `max_active_rewards`
                // migration's note on why this is distinct from
                // `active_reward_campaigns` above, which — despite its
                // name — reflects point_rules for historical reasons).
                'active_rewards' => $this->limitSummary($entitlements->limit('max_active_rewards'), $shop, SubscriptionUsage::METRIC_ACTIVE_REWARDS),
            ],
        ]]);
    }

    private function limitSummary(?int $limit, Shop $shop, string $metric): array
    {
        $used = app(UsageTrackerService::class)->current($shop, $metric);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }
}
