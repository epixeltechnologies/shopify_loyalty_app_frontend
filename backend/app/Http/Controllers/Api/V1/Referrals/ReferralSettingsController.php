<?php

namespace App\Http\Controllers\Api\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referrals\UpdateReferralSettingsRequest;
use App\Services\Referrals\ReferralSettingsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class ReferralSettingsController extends Controller
{
    public function __construct(private readonly ReferralSettingsService $service) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->service->getOrCreate(TenantContext::shop())]);
    }

    public function update(UpdateReferralSettingsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->update(TenantContext::shop(), $request->validated())]);
    }
}
