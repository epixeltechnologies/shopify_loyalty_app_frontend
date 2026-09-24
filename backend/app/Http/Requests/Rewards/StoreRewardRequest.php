<?php

namespace App\Http\Requests\Rewards;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreRewardRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'type' => ['required', Rule::in(['percentage_discount', 'fixed_discount', 'free_shipping', 'gift'])],
            'points_cost' => ['required', 'integer', 'min:1'],
            'value' => ['required', 'array'],
            'min_purchase_amount_cents' => ['nullable', 'integer', 'min:0'],
            'max_discount_amount_cents' => ['nullable', 'integer', 'min:0'],
            'included_product_ids' => ['nullable', 'array'],
            'included_product_ids.*' => ['string'],
            'excluded_product_ids' => ['nullable', 'array'],
            'excluded_product_ids.*' => ['string'],
            'included_collection_ids' => ['nullable', 'array'],
            'included_collection_ids.*' => ['string'],
            'excluded_collection_ids' => ['nullable', 'array'],
            'excluded_collection_ids.*' => ['string'],
            'customer_eligibility' => ['nullable', 'array'],
            'customer_eligibility.type' => ['sometimes', Rule::in(['all', 'vip_tier_minimum', 'specific_customers'])],
            'max_total_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_redemptions_per_customer' => ['nullable', 'integer', 'min:1'],
            'stock_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
        ];
    }
}
