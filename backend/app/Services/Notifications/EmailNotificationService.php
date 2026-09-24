<?php

namespace App\Services\Notifications;

use App\Mail\LoyaltyNotificationMail;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EMAIL: the actual send + log step — always queued
 * (`Mail::to(...)->queue()`, never `->send()`), so a burst of
 * high-volume events (e.g. a bulk points-earned run) never blocks the
 * request/job that triggered it on outbound email delivery. See
 * docs/SETTINGS.md#email-architecture.
 *
 * PROVIDER ABSTRACTION: deliberately does NOT implement a custom
 * provider-switching layer — Laravel's own `Mail` facade already is
 * that abstraction (config/mail.php's `mailers` array supports ses/
 * mailgun/smtp/postmark/etc., swapped via the `MAIL_MAILER` env var,
 * with all credentials living in `.env`/config, never touching this
 * code or the frontend). Building a second, parallel provider
 * abstraction on top of Laravel's own would be exactly the kind of
 * duplication this task asks to avoid.
 */
class EmailNotificationService
{
    public function send(Shop $shop, ?Customer $customer, string $notificationType, string $recipient, string $subject, string $bodyHtml): EmailLog
    {
        $log = EmailLog::query()->create([
            'shop_id' => $shop->id,
            'customer_id' => $customer?->id,
            'notification_type' => $notificationType,
            'recipient' => $recipient,
            'status' => EmailLog::STATUS_QUEUED,
        ]);

        try {
            Mail::to($recipient)->queue(new LoyaltyNotificationMail($subject, $bodyHtml));
            // Queuing succeeded — actual delivery success/failure at the
            // provider level happens later, asynchronously, and isn't
            // observable from here; "sent" here means "handed off to
            // the queue," matching how EmailLog's own `sent_at` doc
            // note should be read. A provider-level bounce/failure
            // would need a webhook from the provider (SES/Mailgun/etc.)
            // to update this row after the fact — out of scope for this
            // milestone, noted in docs/SETTINGS.md.
            $log->update(['status' => EmailLog::STATUS_SENT, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Failed to queue notification email', ['email_log_id' => $log->id, 'error' => $e->getMessage()]);
            $log->update(['status' => EmailLog::STATUS_FAILED, 'failure_reason' => $e->getMessage()]);
        }

        return $log;
    }
}
