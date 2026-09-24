<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** ANALYTICS: pre-aggregated read path — see migration comment. */
class AnalyticsDailySnapshot extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = ['shop_id', 'date', 'metric', 'value'];

    protected function casts(): array
    {
        return ['date' => 'date', 'value' => 'decimal:2'];
    }
}
