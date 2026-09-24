<?php

namespace App\Events\VipTiers;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VipTierChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly ?int $previousTierId,
        public readonly ?int $newTierId,
        public readonly string $direction = 'initial',
    ) {}
}
