<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** REFERRALS: the actual payout for a completed referral — see migration comment. */
class ReferralReward extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id', 'referral_id', 'customer_id', 'beneficiary', 'reward_type',
        'points_awarded', 'point_transaction_id', 'shopify_discount_code', 'granted_at',
    ];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pointTransaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class);
    }
}
