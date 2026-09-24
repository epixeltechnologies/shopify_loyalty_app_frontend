<?php

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rewards\StoreRewardRequest;
use App\Http\Requests\Rewards\UpdateRewardRequest;
use App\Http\Resources\RewardResource;
use App\Models\Reward;
use App\Services\Rewards\RewardService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class RewardController extends Controller
{
    public function __construct(private readonly RewardService $service) {}

    public function index(): JsonResponse
    {
        return RewardResource::collection(
            $this->service->paginate(TenantContext::shop())
        )->response();
    }

    public function show(Reward $reward): JsonResponse
    {
        $this->authorize('view', $reward);

        return (new RewardResource($reward))->response();
    }

    /**
     * The active, redeemable catalog — "Get available rewards" from
     * the Customer Experience Foundation section. Distinct from
     * eligible(): this is the full active catalog regardless of any
     * specific customer's eligibility; CustomerRewardController::eligible()
     * is the customer-specific filtered view.
     */
    public function available(): JsonResponse
    {
        return RewardResource::collection($this->service->catalog(TenantContext::shop()))->response();
    }

    public function store(StoreRewardRequest $request): JsonResponse
    {
        $reward = $this->service->create(TenantContext::shop(), $request->validated());

        return (new RewardResource($reward))->response()->setStatusCode(201);
    }

    public function update(UpdateRewardRequest $request, Reward $reward): JsonResponse
    {
        $this->authorize('update', $reward);

        $reward = $this->service->update(TenantContext::shop(), $reward, $request->validated());

        return (new RewardResource($reward))->response();
    }
}
