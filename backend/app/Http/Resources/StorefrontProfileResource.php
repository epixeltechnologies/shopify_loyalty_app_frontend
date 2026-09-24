<?php

namespace App\Http\Resources;

use App\Services\Points\PointAccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The consolidated "loyalty profile" the storefront widget's landing
 * view needs in one call — composed from the SAME services the admin-
 * facing endpoints already use (PointAccountService), never a
 * re-derivation of their numbers. Never the customer's internal
 * database ID, Shopify customer ID as a queryable key, or anything
 * beyond what the task's "Loyalty Profile" section explicitly lists.
 *
 * Deliberately does NOT compute next-tier progress here — that's a
 * heavier, tier-comparison computation (VipQualificationService) with
 * its own dedicated endpoint (StorefrontVipController::progress()); a
 * widget's landing view can fetch it separately rather than paying that
 * cost on every profile load, and this resource never duplicates that
 * calculation.
 *
 * @mixin \App\Models\Customer
 */
class StorefrontProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pointSummary = app(PointAccountService::class)->summary($this->resource);

        return [
            'first_name' => $this->first_name,
            'points_balance' => $pointSummary['balance'],
            'lifetime_points_earned' => $pointSummary['lifetime_earned'],
            'lifetime_points_redeemed' => $pointSummary['lifetime_redeemed'],
            'current_vip_tier' => $this->vipTier ? new PublicVipTierResource($this->vipTier) : null,
            'referral_code' => $this->referral_code,
        ];
    }
}
