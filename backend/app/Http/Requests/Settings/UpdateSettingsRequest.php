<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\BaseFormRequest;
use App\Rules\HexColor;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // --- General ---------------------------------------------
            'program_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'program_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'program_status' => ['sometimes', Rule::in(['active', 'paused'])],
            'logo_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'widget_primary_color' => ['sometimes', 'string', new HexColor],
            'brand_secondary_color' => ['sometimes', 'nullable', 'string', new HexColor],
            'messaging' => ['sometimes', 'nullable', 'array'],

            // --- Points --------------------------------------------------
            'widget_enabled' => ['sometimes', 'boolean'],
            'points_expiry_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'points_earning_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'allow_negative_point_balance' => ['sometimes', 'boolean'],
            'allow_manual_point_adjustments' => ['sometimes', 'boolean'],

            'notification_email' => ['sometimes', 'nullable', 'email'],
        ];
    }
}
