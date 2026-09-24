<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reward */
class RewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'type' => $this->type,
            'points_cost' => $this->points_cost,
            'value' => $this->value,
            'min_purchase_amount_cents' => $this->min_purchase_amount_cents,
            'max_discount_amount_cents' => $this->max_discount_amount_cents,
            'included_product_ids' => $this->included_product_ids,
            'excluded_product_ids' => $this->excluded_product_ids,
            'included_collection_ids' => $this->included_collection_ids,
            'excluded_collection_ids' => $this->excluded_collection_ids,
            'customer_eligibility' => $this->customer_eligibility,
            'max_total_redemptions' => $this->max_total_redemptions,
            'max_redemptions_per_customer' => $this->max_redemptions_per_customer,
            'stock_limit' => $this->stock_limit,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
