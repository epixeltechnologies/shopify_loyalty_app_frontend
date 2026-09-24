<?php

namespace App\Http\Controllers\Api\V1\PointRules;

use App\Http\Controllers\Controller;
use App\Http\Requests\PointRules\StorePointRuleRequest;
use App\Http\Requests\PointRules\UpdatePointRuleRequest;
use App\Http\Resources\PointRuleResource;
use App\Models\PointRule;
use App\Services\Points\PointRuleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class PointRuleController extends Controller
{
    public function __construct(private readonly PointRuleService $service) {}

    public function index(): JsonResponse
    {
        return PointRuleResource::collection(
            $this->service->paginate(TenantContext::shop())
        )->response();
    }

    public function show(PointRule $pointRule): JsonResponse
    {
        $this->authorize('view', $pointRule);

        return (new PointRuleResource($pointRule))->response();
    }

    public function store(StorePointRuleRequest $request): JsonResponse
    {
        $rule = $this->service->create(TenantContext::shop(), $request->validated());

        return (new PointRuleResource($rule))->response()->setStatusCode(201);
    }

    public function update(UpdatePointRuleRequest $request, PointRule $pointRule): JsonResponse
    {
        $this->authorize('update', $pointRule);

        $rule = $this->service->update(TenantContext::shop(), $pointRule, $request->validated());

        return (new PointRuleResource($rule))->response();
    }
}
