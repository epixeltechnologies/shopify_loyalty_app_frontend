<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** VIP: append-only tier-change log — see migration comment. */
class CustomerVipHistory extends Model
{
    use BelongsToShop, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'customer_vip_history';

    protected $fillable = [
        'shop_id', 'customer_id', 'from_vip_tier_id', 'to_vip_tier_id',
        'direction', 'qualification_reason', 'evaluation_period',
        'lifetime_points_at_change', 'effective_date',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromTier(): BelongsTo
    {
        return $this->belongsTo(VipTier::class, 'from_vip_tier_id');
    }

    public function toTier(): BelongsTo
    {
        return $this->belongsTo(VipTier::class, 'to_vip_tier_id');
    }
}
