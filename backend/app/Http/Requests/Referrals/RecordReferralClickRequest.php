<?php

namespace App\Http\Requests\Referrals;

use App\Http\Requests\BaseFormRequest;

class RecordReferralClickRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'referral_code' => ['required', 'string', 'max:64'],
            'visitor_token' => ['required', 'string', 'max:255'],
        ];
    }
}
