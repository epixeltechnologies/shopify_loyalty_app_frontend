<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\DateRangeRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\RewardRedemptionResource;
use App\Services\Analytics\DateRangeResolver;
use App\Services\Analytics\ReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * ANALYTICS: the seven report types, one method each — every method
 * shares the same date-range resolution (`DateRangeRequest` +
 * `DateRangeResolver`) and delegates all computation to `ReportService`,
 * which the CSV export job also calls, so a report and its export can
 * never disagree. "Advanced reporting" (Professional-only) isn't a
 * different set of endpoints — see routes/api.php: the whole
 * `/analytics/reports/*` group sits behind `feature:analytics.basic`,
 * while the deeper per-customer breakdowns some reports could grow
 * (cohort/retention-style detail) are reserved for
 * `feature:analytics.advanced`, consistent with how
 * AnalyticsService::cohortRetention() was already scaffolded.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly DateRangeResolver $dates,
    ) {}

    public function customerGrowth(DateRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->customerGrowth(...$this->rangeWithShop($request))]);
    }

    public function points(DateRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->points(...$this->rangeWithShop($request))]);
    }

    public function rewards(DateRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->rewards(...$this->rangeWithShop($request))]);
    }

    public function redemptions(DateRangeRequest $request): JsonResponse
    {
        [$shop, $start, $end] = $this->rangeWithShop($request);

        return RewardRedemptionResource::collection(
            $this->reports->redemptions($shop, $start, $end, $request->integer('per_page', 25))
        )->response();
    }

    public function referrals(DateRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->referrals(...$this->rangeWithShop($request))]);
    }

    public function vip(DateRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->vip(...$this->rangeWithShop($request))]);
    }

    public function engagement(DateRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->loyaltyEngagement(...$this->rangeWithShop($request))]);
    }

    /** @return array{0: \App\Models\Shop, 1: \Illuminate\Support\Carbon, 2: \Illuminate\Support\Carbon} */
    private function rangeWithShop(DateRangeRequest $request): array
    {
        $shop = TenantContext::shop();
        [$start, $end] = $this->dates->resolve($shop, $request->validated('preset', 'last_30_days'), $request->validated('from'), $request->validated('to'));

        return [$shop, $start, $end];
    }
}
