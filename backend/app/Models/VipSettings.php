<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** SETTINGS: the shop-level VIP program switch — see migration comment for why qualification/period/benefits stay per-tier, not here. */
class VipSettings extends Model
{
    use BelongsToShop, HasFactory;

    protected $table = 'vip_settings';

    protected $fillable = ['shop_id', 'enabled', 'notify_on_upgrade', 'notify_on_downgrade'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'notify_on_upgrade' => 'boolean', 'notify_on_downgrade' => 'boolean'];
    }
}
