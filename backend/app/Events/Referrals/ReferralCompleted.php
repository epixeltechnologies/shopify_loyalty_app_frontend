<?php

namespace App\Events\Referrals;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Referral $referral) {}
}
