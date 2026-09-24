<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicVipTierResource;
use App\Services\VipTiers\VipBenefitService;
use App\Services\VipTiers\VipQualificationService;
use App\Support\Tenancy\CustomerContext;
use Illuminate\Http\JsonResponse;

/**
 * Reuses VipQualificationService (the exact same qualification/progress
 * math CustomerVipController's admin-facing equivalent uses) and
 * VipBenefitService — no separate storefront progress calculation.
 */
class StorefrontVipController extends Controller
{
    public function __construct(
        private readonly VipQualificationService $qualification,
        private readonly VipBenefitService $benefits,
    ) {}

    public function current(): JsonResponse
    {
        $tier = CustomerContext::customer()->vipTier;

        return response()->json(['data' => $tier ? new PublicVipTierResource($tier) : null]);
    }

    public function progress(): JsonResponse
    {
        $progress = $this->qualification->progressToNextTier(CustomerContext::customer());

        if (! $progress['next_tier']) {
            return response()->json(['data' => ['next_tier' => null, 'message' => "You've reached the highest available tier."]]);
        }

        $progress['next_tier'] = new PublicVipTierResource($progress['next_tier']);

        return response()->json(['data' => $progress]);
    }

    public function benefits(): JsonResponse
    {
        return response()->json(['data' => $this->benefits->all(CustomerContext::customer())]);
    }
}
