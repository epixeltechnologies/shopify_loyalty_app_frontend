<?php

namespace App\Http\Requests\Notifications;

use App\Http\Requests\BaseFormRequest;

class UpdateNotificationSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
