<?php

namespace App\Http\Controllers\Api\V1\VipTiers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VipTiers\StoreVipTierRequest;
use App\Http\Requests\VipTiers\UpdateVipTierRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\VipTierResource;
use App\Jobs\VipTiers\EvaluateShopVipTiersJob;
use App\Models\VipTier;
use App\Services\VipTiers\VipTierService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VipTierController extends Controller
{
    public function __construct(private readonly VipTierService $service) {}

    public function index(): JsonResponse
    {
        return VipTierResource::collection(
            $this->service->listForShop(TenantContext::shop())
        )->response();
    }

    public function show(VipTier $vipTier): JsonResponse
    {
        $this->authorize('view', $vipTier);

        return (new VipTierResource($vipTier->loadCount('customers')))->response();
    }

    public function store(StoreVipTierRequest $request): JsonResponse
    {
        $this->authorize('create', [VipTier::class, $request->validated('slug')]);

        $tier = VipTier::query()->create([...$request->validated(), 'shop_id' => TenantContext::shopId()]);

        return (new VipTierResource($tier))->response()->setStatusCode(201);
    }

    public function update(UpdateVipTierRequest $request, VipTier $vipTier): JsonResponse
    {
        $this->authorize('update', $vipTier);

        $vipTier->update($request->validated());

        return (new VipTierResource($vipTier->fresh()->loadCount('customers')))->response();
    }

    /** Bulk re-order — the "drag/reorder" admin UI action. Body: `{ "order": [tierIdInPosition0, tierIdInPosition1, ...] }`. */
    public function reorder(Request $request): JsonResponse
    {
        $shop = TenantContext::shop();
        $ids = $request->input('order', []);

        foreach ($ids as $position => $id) {
            VipTier::query()->where('shop_id', $shop->id)->where('id', $id)->update(['sort_order' => $position]);
        }

        return VipTierResource::collection($this->service->listForShop($shop))->response();
    }

    /** Manual evaluation — an admin-triggered run of the same scheduled evaluation, for "I just changed the rules, evaluate everyone now" rather than waiting for the next daily run. */
    public function evaluate(): JsonResponse
    {
        EvaluateShopVipTiersJob::dispatch(TenantContext::shop());

        return response()->json(['data' => ['message' => 'VIP tier evaluation has been queued.']]);
    }

    /** The "Customer VIP List" page — every customer currently in this tier. */
    public function customers(VipTier $vipTier): JsonResponse
    {
        $this->authorize('view', $vipTier);

        return CustomerResource::collection(
            $vipTier->customers()->paginate(25)
        )->response();
    }

    /** The "show downgrade warning, require merchant action" surface — see VipTierService::customersAffectedByPlanDowngrade(). */
    public function downgradeWarnings(): JsonResponse
    {
        $customers = $this->service->customersAffectedByPlanDowngrade(TenantContext::shop());

        return CustomerResource::collection($customers)->response();
    }
}
