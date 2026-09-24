<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ORDER LINE ITEMS: one row per Shopify line item — see migration comment for the product/collection-rule preparation rationale. */
class OrderLineItem extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'shop_id', 'order_id', 'shopify_line_item_id', 'shopify_product_id', 'shopify_variant_id',
        'title', 'sku', 'vendor', 'product_type', 'quantity', 'price_cents', 'total_discount_cents',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
