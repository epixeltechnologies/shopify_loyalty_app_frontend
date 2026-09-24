<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** POINT SYSTEM: how points are earned — see migration comment. */
class PointRule extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id', 'name', 'type', 'config', 'status', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
