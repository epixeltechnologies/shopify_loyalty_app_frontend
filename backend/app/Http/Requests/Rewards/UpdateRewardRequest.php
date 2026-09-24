<?php

namespace App\Http\Requests\Rewards;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateRewardRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'type' => ['sometimes', Rule::in(['percentage_discount', 'fixed_discount', 'free_shipping', 'gift'])],
            'points_cost' => ['sometimes', 'integer', 'min:1'],
            'value' => ['sometimes', 'array'],
            'min_purchase_amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_discount_amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'included_product_ids' => ['sometimes', 'nullable', 'array'],
            'excluded_product_ids' => ['sometimes', 'nullable', 'array'],
            'included_collection_ids' => ['sometimes', 'nullable', 'array'],
            'excluded_collection_ids' => ['sometimes', 'nullable', 'array'],
            'customer_eligibility' => ['sometimes', 'nullable', 'array'],
            'max_total_redemptions' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_redemptions_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'stock_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
        ];
    }
}
