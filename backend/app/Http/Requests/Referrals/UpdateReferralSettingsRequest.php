<?php

namespace App\Http\Requests\Referrals;

use App\Http\Requests\BaseFormRequest;

class UpdateReferralSettingsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'referrer_reward_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'referee_reward_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimum_qualifying_order_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'require_first_purchase' => ['sometimes', 'boolean'],
            'reward_delay_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'attribution_window_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'max_referrals_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
