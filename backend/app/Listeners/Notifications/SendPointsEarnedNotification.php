<?php

namespace App\Listeners\Notifications;

use App\Events\Points\PointsPosted;
use App\Models\PointTransaction;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fires the generic "points earned" notification for a plain earn
 * transaction — but NOT for sources that already have their own
 * dedicated notification type elsewhere (birthday_reward,
 * referral_reward/referral_bonus), since those are also posted via
 * `direction: earn` and would otherwise double-notify the customer:
 * once here, generically, and once from the type-specific listener
 * (SendVipTierChangedNotification's sibling for birthdays lives in
 * AwardBirthdayPointsJob; referral rewards in ReferralRewardService).
 * Signup bonus is deliberately NOT excluded — the task's 9 notification
 * types have no separate "account creation" entry, so a signup bonus
 * is exactly what the generic points-earned notification is for.
 */
class SendPointsEarnedNotification implements ShouldQueue
{
    private const EXCLUDED_SOURCES = [
        PointTransaction::SOURCE_BIRTHDAY_REWARD,
        PointTransaction::SOURCE_REFERRAL_REWARD,
        PointTransaction::SOURCE_REFERRAL_BONUS,
    ];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PointsPosted $event): void
    {
        $transaction = $event->transaction;

        if ($transaction->direction !== PointTransaction::DIRECTION_EARN) {
            return;
        }

        if (in_array($transaction->source, self::EXCLUDED_SOURCES, true)) {
            return;
        }

        $customer = $transaction->customer;

        $this->notifications->notify($customer->shop, $customer, 'points_earned', [
            'points' => (string) $transaction->points,
            'balance' => (string) ($customer->point?->balance ?? 0),
        ]);
    }
}
