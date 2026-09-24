<?php

namespace App\Http\Controllers\Api\V1\VipTiers;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicVipTierResource;
use App\Models\Customer;
use App\Models\CustomerVipHistory;
use App\Services\VipTiers\VipBenefitService;
use App\Services\VipTiers\VipQualificationService;
use Illuminate\Http\JsonResponse;

/**
 * The "Customer API" section — prepared for a future storefront widget,
 * same scope note as CustomerRewardController/CustomerReferralController.
 * See App\Http\Controllers\Api\V1\Storefront for the now-real storefront
 * equivalent of this controller, which shares its underlying service
 * calls rather than duplicating them.
 */
class CustomerVipController extends Controller
{
    public function __construct(
        private readonly VipQualificationService $qualification,
        private readonly VipBenefitService $benefits,
    ) {}

    /** Get current VIP tier. */
    public function current(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(['data' => $customer->vipTier ? new PublicVipTierResource($customer->vipTier) : null]);
    }

    /**
     * Get progress to next tier — how close the customer is, in terms
     * of their current tier's own qualification method (each tier can
     * use a different method, so "progress" is only ever measured
     * against ONE specific next tier's own requirement). The actual
     * computation lives in VipQualificationService::progressToNextTier()
     * — shared with the storefront equivalent of this endpoint, not
     * duplicated per controller.
     */
    public function progress(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $progress = $this->qualification->progressToNextTier($customer);

        if (! $progress['next_tier']) {
            return response()->json(['data' => ['next_tier' => null, 'message' => 'Already at the highest available tier, or no higher tier is configured.']]);
        }

        $progress['next_tier'] = new PublicVipTierResource($progress['next_tier']);

        return response()->json(['data' => $progress]);
    }

    /** Get tier benefits. */
    public function benefits(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(['data' => $this->benefits->all($customer)]);
    }

    /** Get tier history. */
    public function history(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $history = CustomerVipHistory::query()
            ->where('customer_id', $customer->id)
            ->with(['fromTier', 'toTier'])
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => $history->map(fn (CustomerVipHistory $row) => [
                'from_tier' => $row->fromTier?->name,
                'to_tier' => $row->toTier?->name,
                'direction' => $row->direction,
                'effective_date' => $row->effective_date?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $history->currentPage(), 'last_page' => $history->lastPage(), 'total' => $history->total()],
        ]);
    }
}
