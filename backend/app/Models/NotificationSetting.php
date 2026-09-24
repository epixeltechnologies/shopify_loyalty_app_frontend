<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** SETTINGS/NOTIFICATIONS: one row per (shop, notification type) — see migration comment. */
class NotificationSetting extends Model
{
    use BelongsToShop, HasFactory;

    public const TYPE_WELCOME = 'welcome';

    public const TYPE_POINTS_EARNED = 'points_earned';

    public const TYPE_REWARD_REDEEMED = 'reward_redeemed';

    public const TYPE_BIRTHDAY_REWARD = 'birthday_reward';

    public const TYPE_REFERRAL_REWARD = 'referral_reward';

    public const TYPE_VIP_UPGRADED = 'vip_upgraded';

    public const TYPE_VIP_DOWNGRADED = 'vip_downgraded';

    public const TYPE_POINTS_EXPIRING = 'points_expiring';

    public const TYPE_REWARD_EXPIRING = 'reward_expiring';

    public const TYPES = [
        self::TYPE_WELCOME, self::TYPE_POINTS_EARNED, self::TYPE_REWARD_REDEEMED,
        self::TYPE_BIRTHDAY_REWARD, self::TYPE_REFERRAL_REWARD,
        self::TYPE_VIP_UPGRADED, self::TYPE_VIP_DOWNGRADED,
        self::TYPE_POINTS_EXPIRING, self::TYPE_REWARD_EXPIRING,
    ];

    protected $fillable = ['shop_id', 'type', 'enabled', 'subject', 'message'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
