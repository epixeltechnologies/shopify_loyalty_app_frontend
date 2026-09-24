<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RewardRedemption */
class RewardRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'reward' => new RewardResource($this->whenLoaded('reward')),
            'points_spent' => $this->points_spent,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'status' => $this->status,
            'shopify_discount_code' => $this->shopify_discount_code,
            'failure_reason' => $this->when($this->status === 'failed', $this->failure_reason),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
