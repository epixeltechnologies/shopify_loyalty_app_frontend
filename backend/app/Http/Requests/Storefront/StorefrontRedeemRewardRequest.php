<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\BaseFormRequest;

class StorefrontRedeemRewardRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
