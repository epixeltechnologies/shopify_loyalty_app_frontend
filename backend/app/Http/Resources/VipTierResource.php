<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VipTier */
class VipTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'qualification_method' => $this->qualification_method,
            'threshold_points' => $this->threshold_points,
            'minimum_spend_cents' => $this->minimum_spend_cents,
            'minimum_orders' => $this->minimum_orders,
            'evaluation_period' => $this->evaluation_period,
            'rolling_period_days' => $this->rolling_period_days,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'perks' => $this->perks,
            'is_active' => $this->is_active,
            'is_available_to_shop' => $this->isAvailableToShop(),
            'customer_count' => $this->whenCounted('customers'),
        ];
    }
}
