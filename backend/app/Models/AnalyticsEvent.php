<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ANALYTICS: raw event feed — see migration comment. Write-heavy, never updated. */
class AnalyticsEvent extends Model
{
    use BelongsToShop, HasFactory;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'occurred_at';

    protected $fillable = ['shop_id', 'customer_id', 'event_type', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'occurred_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
