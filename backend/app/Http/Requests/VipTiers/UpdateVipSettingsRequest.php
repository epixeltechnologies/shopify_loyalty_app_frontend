<?php

namespace App\Http\Requests\VipTiers;

use App\Http\Requests\BaseFormRequest;

class UpdateVipSettingsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'notify_on_upgrade' => ['sometimes', 'boolean'],
            'notify_on_downgrade' => ['sometimes', 'boolean'],
        ];
    }
}
