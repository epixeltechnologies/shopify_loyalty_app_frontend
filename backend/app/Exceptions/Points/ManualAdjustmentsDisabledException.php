<?php

namespace App\Exceptions\Points;

use RuntimeException;

/** Thrown when a shop has turned off manual point adjustments (`shop_settings.allow_manual_point_adjustments`) — the "manual adjustment permissions" point setting. */
class ManualAdjustmentsDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Manual point adjustments are disabled for this shop.');
    }
}
