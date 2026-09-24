<?php

namespace App\Support\Str;

use App\Models\Shop;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Str;

class ReferralCodeGenerator
{
    public function __construct(private readonly CustomerRepositoryInterface $customers) {}

    public function generateUnique(Shop $shop, int $length = 8): string
    {
        do {
            $code = Str::upper(Str::random($length));
        } while ($this->customers->findByReferralCode($shop, $code) !== null);

        return $code;
    }
}
