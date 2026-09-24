<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SYSTEM: security/audit trail. Deliberately does NOT use BelongsToShop
 * — see migration comment.
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'shop_id', 'user_id', 'actor_type', 'action',
        'auditable_type', 'auditable_id', 'changes', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
