<?php

namespace App\Http\Controllers\Api\V1\VipTiers;

use App\Http\Controllers\Controller;
use App\Http\Requests\VipTiers\UpdateVipSettingsRequest;
use App\Models\VipSettings;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/** The shop-level VIP program switch — see `vip_settings` migration's note on why qualification/period/benefits stay per-tier, not here. */
class VipSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(): JsonResponse
    {
        $shop = TenantContext::shop();

        return response()->json(['data' => VipSettings::query()->firstOrCreate(['shop_id' => $shop->id])]);
    }

    public function update(UpdateVipSettingsRequest $request): JsonResponse
    {
        $shop = TenantContext::shop();
        $settings = VipSettings::query()->firstOrCreate(['shop_id' => $shop->id]);
        $before = $settings->only(array_keys($request->validated()));

        $settings->update($request->validated());

        $this->audit->logModelChange($shop, 'vip_settings.updated', $settings, $before);

        return response()->json(['data' => $settings->fresh()]);
    }
}
