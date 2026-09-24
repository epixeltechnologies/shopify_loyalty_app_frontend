<?php

namespace App\Http\Requests\Rewards;

use App\Http\Requests\BaseFormRequest;

class RedeemRewardRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Client-supplied, optional — see RewardRedemptionService's
            // idempotency handling. The frontend should generate one
            // UUID per redemption attempt (persisted in component state
            // until the request succeeds) so a double-click or a
            // retried request never creates two redemptions.
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
