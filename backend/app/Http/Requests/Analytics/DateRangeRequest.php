<?php

namespace App\Http\Requests\Analytics;

use App\Http\Requests\BaseFormRequest;
use App\Services\Analytics\DateRangeResolver;
use Illuminate\Validation\Rule;

class DateRangeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'preset' => ['sometimes', Rule::in(DateRangeResolver::PRESETS)],
            'from' => ['required_if:preset,custom', 'nullable', 'date'],
            'to' => ['required_if:preset,custom', 'nullable', 'date', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
