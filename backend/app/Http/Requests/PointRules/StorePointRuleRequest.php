<?php

namespace App\Http\Requests\PointRules;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePointRuleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['points_per_dollar', 'signup_bonus', 'referral_bonus', 'birthday_bonus', 'review_reward', 'custom'])],
            'config' => ['required', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
