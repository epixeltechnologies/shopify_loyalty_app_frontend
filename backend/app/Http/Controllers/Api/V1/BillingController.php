<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Billing\DowngradeBlockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\DowngradeCheckRequest;
use App\Http\Requests\Billing\SubscribeRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionEventResource;
use App\Models\SubscriptionEvent;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageTrackerService;
use App\Models\SubscriptionUsage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * No business logic lives here — every branch delegates to
 * BillingService / SubscriptionService / PlanService. This app has no
 * free tier, so `subscribe` is the entry point every newly installed
 * shop must hit before any other route unlocks. See docs/BILLING.md.
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly PlanRepositoryInterface $plans,
        private readonly BillingService $billing,
        private readonly SubscriptionService $subscriptions,
        private readonly PlanService $planService,
    ) {}

    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => PlanResource::collection($this->plans->all()),
        ]);
    }

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        try {
            $confirmationUrl = $this->billing->createSubscription(
                shop: TenantContext::shop(),
                planSlug: $request->validated('plan'),
            );
        } catch (DowngradeBlockedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'DOWNGRADE_BLOCKED',
                ...$e->eligibility->toArray(),
            ], 422);
        }

        return response()->json(['confirmation_url' => $confirmationUrl]);
    }

    /**
     * Lightweight boot-time check — see routes/api.php and
     * frontend/src/routes/ProtectedRoute.tsx, which calls this once to
     * decide whether to render the app or BillingRequiredPage.
     */
    public function status(): JsonResponse
    {
        $shop = TenantContext::shop();

        return response()->json([
            'has_active_subscription' => $shop->entitlements()->hasActiveSubscription(),
            'subscription' => $shop->activeSubscription?->only(['status', 'current_period_end']),
            'plan' => $shop->activeSubscription?->plan
                ? new PlanResource($shop->activeSubscription->plan)
                : null,
        ]);
    }

    /** Richer subscription detail for the /subscription page — status, dates, plan, recent history. */
    public function subscription(): JsonResponse
    {
        $shop = TenantContext::shop();
        $subscription = $this->subscriptions->current($shop);

        return response()->json(['data' => [
            'status' => $subscription?->status,
            'plan' => $subscription?->plan ? new PlanResource($subscription->plan) : null,
            'current_period_start' => $subscription?->current_period_start?->toIso8601String(),
            'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'cancelled_at' => $subscription?->cancelled_at?->toIso8601String(),
            'recent_events' => SubscriptionEventResource::collection(
                SubscriptionEvent::query()->where('shop_id', $shop->id)->latest('occurred_at')->limit(10)->get()
            ),
        ]]);
    }

    /** Current usage vs. plan limits — powers the usage bars on /billing and /plans. */
    public function usage(): JsonResponse
    {
        $shop = TenantContext::shop();
        $entitlements = $shop->entitlements();
        $usage = app(UsageTrackerService::class);

        return response()->json(['data' => [
            'active_customers' => [
                'used' => $usage->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS),
                'limit' => $entitlements->limit('max_active_customers'),
            ],
            'active_point_rules' => [
                'used' => $usage->current($shop, SubscriptionUsage::METRIC_ACTIVE_POINT_RULES),
                'limit' => $entitlements->limit('max_active_point_rules'),
            ],
        ]]);
    }

    /**
     * Lets the frontend show downgrade warnings BEFORE the merchant
     * commits — the same check `subscribe()` enforces server-side
     * (the real boundary), surfaced here as a non-mutating preview.
     */
    public function downgradeCheck(DowngradeCheckRequest $request): JsonResponse
    {
        $targetPlan = $this->planService->findBySlug($request->validated('plan'));

        return response()->json(
            $this->planService->checkDowngradeEligibility(TenantContext::shop(), $targetPlan)->toArray()
        );
    }
}
