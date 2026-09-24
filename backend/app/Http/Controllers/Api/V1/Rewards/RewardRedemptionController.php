<?php

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rewards\RedeemRewardRequest;
use App\Http\Resources\RewardRedemptionResource;
use App\Models\Customer;
use App\Models\Reward;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use App\Services\Rewards\RewardRedemptionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class RewardRedemptionController extends Controller
{
    public function __construct(
        private readonly RewardRedemptionService $service,
        private readonly RewardRedemptionRepositoryInterface $redemptions,
    ) {}

    /** Shop-wide redemption history — the merchant admin's "Redemption History" page. */
    public function index(): JsonResponse
    {
        return RewardRedemptionResource::collection(
            $this->redemptions->paginateForShop(TenantContext::shop())
        )->response();
    }

    public function store(RedeemRewardRequest $request, Customer $customer, Reward $reward): JsonResponse
    {
        $this->authorize('redeem', $customer);

        $redemption = $this->service->redeem($customer, $reward, $request->validated('idempotency_key'));

        return (new RewardRedemptionResource($redemption->load(['reward', 'customer'])))->response()->setStatusCode(201);
    }
}
