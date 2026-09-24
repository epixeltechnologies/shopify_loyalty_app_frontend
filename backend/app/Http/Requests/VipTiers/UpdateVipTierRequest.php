<?php

namespace App\Http\Requests\VipTiers;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateVipTierRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'qualification_method' => ['sometimes', Rule::in(['points_earned', 'total_spend', 'order_count'])],
            'threshold_points' => ['sometimes', 'integer', 'min:0'],
            'minimum_spend_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimum_orders' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'evaluation_period' => ['sometimes', Rule::in(['lifetime', 'calendar_year', 'rolling'])],
            'rolling_period_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1825'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'perks' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
