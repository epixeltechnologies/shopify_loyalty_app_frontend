<?php

namespace App\Http\Controllers\Api\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Models\Referral;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralRepositoryInterface $referrals) {}

    /** The "Referral List" page — optionally filtered by status (e.g. `?status=qualified` for a fraud-review-adjacent view). */
    public function index(Request $request): JsonResponse
    {
        return ReferralResource::collection(
            $this->referrals->paginateForShop(TenantContext::shop(), status: $request->query('status'))
        )->response();
    }

    /** The "Referral Details" page. */
    public function show(Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);

        return (new ReferralResource($referral->load(['referrer', 'referred', 'rewards'])))->response();
    }

    /** The "Fraud Review" page's list — every referral currently flagged for merchant attention. */
    public function fraudQueue(): JsonResponse
    {
        return ReferralResource::collection(
            Referral::query()
                ->where('shop_id', TenantContext::shop()->id)
                ->where('fraud_status', Referral::FRAUD_STATUS_FLAGGED)
                ->with(['referrer', 'referred'])
                ->latest()
                ->paginate(25)
        )->response();
    }

    /** A merchant reviewing a flagged referral decides it's legitimate — clears it for the next reward-processing run to pay out normally. */
    public function clearFraud(Referral $referral): JsonResponse
    {
        $this->authorize('review', $referral);

        $referral->update(['fraud_status' => Referral::FRAUD_STATUS_CLEARED]);

        return (new ReferralResource($referral->fresh()))->response();
    }

    /** A merchant confirms fraud — permanently blocks this referral from ever being rewarded, regardless of its lifecycle status. */
    public function confirmFraud(Referral $referral): JsonResponse
    {
        $this->authorize('review', $referral);

        $referral->update(['fraud_status' => Referral::FRAUD_STATUS_CONFIRMED]);

        return (new ReferralResource($referral->fresh()))->response();
    }

    /** The "Referral Performance" page's analytics summary. */
    public function statistics(): JsonResponse
    {
        return response()->json(['data' => $this->referrals->statisticsFor(TenantContext::shop())]);
    }
}
