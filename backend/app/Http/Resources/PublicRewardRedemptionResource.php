<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer/storefront-facing counterpart to RewardRedemptionResource
 * — deliberately a SEPARATE, more limited resource rather than
 * conditionally hiding fields on the admin one, so it's structurally
 * impossible for a future storefront-widget endpoint to accidentally
 * leak the merchant-admin field set (internal Shopify discount node
 * ID, the customer relation itself, failure diagnostics) by a copy-
 * paste or a missed `when()` condition. See docs/REWARDS_ENGINE.md's
 * customer-experience-foundation section.
 *
 * @mixin \App\Models\RewardRedemption
 */
class PublicRewardRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reward' => new RewardResource($this->whenLoaded('reward')),
            'points_spent' => $this->points_spent,
            'status' => $this->status,
            'discount_code' => $this->when($this->status === 'completed', $this->shopify_discount_code),
            'redeemed_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
