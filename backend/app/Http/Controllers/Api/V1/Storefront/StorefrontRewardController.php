<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontRedeemRewardRequest;
use App\Http\Resources\PublicRewardRedemptionResource;
use App\Http\Resources\RewardResource;
use App\Models\Reward;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use App\Services\Rewards\RewardRedemptionService;
use App\Services\Rewards\RewardService;
use App\Support\Tenancy\CustomerContext;
use Illuminate\Http\JsonResponse;

/**
 * Redemption reuses RewardRedemptionService::redeem() directly — the
 * EXACT same centralized service the merchant-admin redemption endpoint
 * calls (see docs/REWARDS_ENGINE.md), including its full validation
 * chain (reward active/date-window/stock, customer eligibility,
 * sufficient balance, plan entitlement via the existing subscription
 * gate) and its locking/idempotency guarantees. This controller adds
 * NO redemption logic of its own — it only resolves WHICH customer is
 * redeeming, from CustomerContext, never a request-supplied value.
 */
class StorefrontRewardController extends Controller
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly RewardRedemptionService $redemption,
        private readonly RewardRedemptionRepositoryInterface $redemptions,
    ) {}

    public function available(): JsonResponse
    {
        return RewardResource::collection($this->rewards->catalog(CustomerContext::customer()->shop))->response();
    }

    /** Rewards THIS customer specifically can currently redeem — active, in-window, not at any usage limit, eligibility rules satisfied. */
    public function eligible(): JsonResponse
    {
        return RewardResource::collection($this->rewards->eligibleFor(CustomerContext::customer()))->response();
    }

    public function redeem(StorefrontRedeemRewardRequest $request, Reward $reward): JsonResponse
    {
        $redemption = $this->redemption->redeem(
            CustomerContext::customer(),
            $reward,
            $request->validated('idempotency_key'),
        );

        return (new PublicRewardRedemptionResource($redemption->load('reward')))->response()->setStatusCode(201);
    }

    public function history(): JsonResponse
    {
        return PublicRewardRedemptionResource::collection(
            $this->redemptions->paginateForCustomer(CustomerContext::customer(), 20)
        )->response();
    }
}
