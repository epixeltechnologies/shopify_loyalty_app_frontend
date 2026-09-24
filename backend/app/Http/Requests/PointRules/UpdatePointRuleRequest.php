<?php

namespace App\Http\Requests\PointRules;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePointRuleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['points_per_dollar', 'signup_bonus', 'referral_bonus', 'birthday_bonus', 'review_reward', 'custom'])],
            'config' => ['sometimes', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ];
    }
}
