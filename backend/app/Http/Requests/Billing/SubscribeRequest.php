<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
        ];
    }
}
