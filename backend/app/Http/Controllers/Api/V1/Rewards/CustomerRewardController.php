<?php

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicRewardRedemptionResource;
use App\Http\Resources\RewardResource;
use App\Models\Customer;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use App\Services\Rewards\RewardService;
use Illuminate\Http\JsonResponse;

/**
 * The "Customer Experience Foundation" endpoints — prepared now for a
 * future storefront loyalty widget, not yet consumed by any real
 * storefront UI. Every response goes through a resource that excludes
 * merchant-only fields (see PublicRewardRedemptionResource) — never the
 * admin-facing RewardRedemptionResource.
 */
class CustomerRewardController extends Controller
{
    public function __construct(
        private readonly RewardService $rewards,
        private readonly RewardRedemptionRepositoryInterface $redemptions,
    ) {}

    /** Every reward this customer could currently redeem — see RewardService::eligibleFor(). */
    public function eligible(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return RewardResource::collection($this->rewards->eligibleFor($customer))->response();
    }

    public function redemptionHistory(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return PublicRewardRedemptionResource::collection(
            $this->redemptions->paginateForCustomer($customer)
        )->response();
    }
}
