<?php

namespace App\Events\Shop;

use App\Models\Shop;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopInstalled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Shop $shop) {}
}
