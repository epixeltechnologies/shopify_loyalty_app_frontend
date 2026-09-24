<?php

namespace App\Listeners\Shop;

use App\Events\Shop\ShopInstalled;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Placeholder: once the loyalty engine ships, this seeds a default points
 * program (e.g. "1 point per $1 spent") for newly installed shops.
 */
class ProvisionDefaultLoyaltyProgram implements ShouldQueue
{
    public function handle(ShopInstalled $event): void
    {
        // Intentionally left for the loyalty-engine milestone.
    }
}
