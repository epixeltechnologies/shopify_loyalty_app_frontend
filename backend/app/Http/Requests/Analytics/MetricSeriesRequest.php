<?php

namespace App\Http\Requests\Analytics;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class MetricSeriesRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'metric' => ['required', Rule::in([
                'new_customers', 'active_members', 'total_customers',
                'points_issued', 'points_redeemed', 'points_expired', 'points_adjusted',
                'reward_redemptions',
                'referrals_created', 'referrals_registered', 'referrals_qualified', 'referrals_completed',
                'vip_upgrades', 'vip_downgrades',
            ])],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }
}
