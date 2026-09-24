<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TENANT SYSTEM: 1:1 with Shop. Split from `shops` so settings reads/
 * writes never contend with the high-traffic tenant-resolution path —
 * see migration comment.
 */
class ShopSetting extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id', 'program_name', 'program_description', 'program_status', 'logo_url',
        'widget_enabled', 'widget_primary_color', 'brand_secondary_color', 'points_expiry_days',
        'allow_negative_point_balance', 'allow_manual_point_adjustments',
        'notification_email', 'points_earning_label', 'messaging', 'extra',
    ];

    protected function casts(): array
    {
        return [
            'widget_enabled' => 'boolean',
            'allow_negative_point_balance' => 'boolean',
            'allow_manual_point_adjustments' => 'boolean',
            'messaging' => 'array',
            'extra' => 'array',
        ];
    }
}
