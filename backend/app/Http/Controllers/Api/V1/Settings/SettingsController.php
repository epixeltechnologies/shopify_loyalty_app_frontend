<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\ShopSetting;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(): JsonResponse
    {
        $shop = TenantContext::shop();
        $settings = $shop->setting ?? ShopSetting::query()->create(['shop_id' => $shop->id]);

        return response()->json(['data' => $settings]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $shop = TenantContext::shop();
        $settings = $shop->setting ?? ShopSetting::query()->create(['shop_id' => $shop->id]);
        $before = $settings->only(array_keys($request->validated()));

        $settings->update($request->validated());

        $this->audit->logModelChange($shop, 'settings.updated', $settings, $before);

        return response()->json(['data' => $settings->fresh()]);
    }
}
