<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** EMAIL: an audit trail row per send attempt — never the rendered body. See migration comment. */
class EmailLog extends Model
{
    use BelongsToShop, HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['shop_id', 'customer_id', 'notification_type', 'recipient', 'status', 'sent_at', 'failure_reason'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
