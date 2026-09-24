<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ORDERS: normalized, loyalty-relevant projection of a Shopify order —
 * see migration comment for what's deliberately excluded and why.
 */
class Order extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'shop_id', 'customer_id', 'shopify_order_id', 'order_number',
        'financial_status', 'fulfillment_status', 'currency',
        'subtotal_cents', 'total_cents', 'total_discounts_cents',
        'total_tax_cents', 'total_shipping_cents',
        'shopify_created_at', 'shopify_updated_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'shopify_created_at' => 'datetime',
            'shopify_updated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(OrderLineItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Convenience accessor — dollars, for display; storage stays in cents (see migration comment). */
    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }
}
