<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** ANALYTICS: one row per requested report export — see migration comment. */
class AnalyticsExport extends Model
{
    use BelongsToShop, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'shop_id', 'report_type', 'status', 'filters', 'file_path', 'row_count', 'failure_reason', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['filters' => 'array', 'expires_at' => 'datetime'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
