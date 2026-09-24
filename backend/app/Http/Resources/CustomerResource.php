<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shopify_customer_id' => $this->shopify_customer_id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'points_balance' => $this->points_balance,
            'lifetime_points_earned' => $this->point?->lifetime_earned ?? 0,
            'vip_tier' => new VipTierResource($this->whenLoaded('vipTier')),
            'referral_code' => $this->referral_code,
            'status' => $this->status,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
        ];
    }
}
