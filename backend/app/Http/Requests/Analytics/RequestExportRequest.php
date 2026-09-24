<?php

namespace App\Http\Requests\Analytics;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class RequestExportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(['customer_growth', 'points', 'rewards', 'redemptions', 'referrals', 'vip', 'engagement'])],
            'preset' => ['required', Rule::in(\App\Services\Analytics\DateRangeResolver::PRESETS)],
            'from' => ['required_if:preset,custom', 'nullable', 'date'],
            'to' => ['required_if:preset,custom', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
