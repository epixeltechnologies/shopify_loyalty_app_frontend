<?php

namespace App\Http\Requests\VipTiers;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreVipTierRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Restricted to the three known slugs — plan restrictions
            // are enforced via the vip_tier.{slug} feature flag (see
            // VipTierPolicy), which only exists for these three; a
            // custom slug would always 403 at the policy anyway, but
            // rejecting it here gives a clearer validation error.
            'slug' => ['required', Rule::in(['silver', 'gold', 'platinum'])],
            'qualification_method' => ['required', Rule::in(['points_earned', 'total_spend', 'order_count'])],
            'threshold_points' => ['required_if:qualification_method,points_earned', 'integer', 'min:0'],
            'minimum_spend_cents' => ['required_if:qualification_method,total_spend', 'integer', 'min:0'],
            'minimum_orders' => ['required_if:qualification_method,order_count', 'integer', 'min:0'],
            'evaluation_period' => ['required', Rule::in(['lifetime', 'calendar_year', 'rolling'])],
            'rolling_period_days' => ['required_if:evaluation_period,rolling', 'integer', 'min:1', 'max:1825'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'perks' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
