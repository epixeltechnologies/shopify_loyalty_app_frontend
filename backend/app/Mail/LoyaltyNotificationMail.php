<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * EMAIL: ONE flexible Mailable for all 9 notification types, rendering
 * whatever subject/body NotificationService has already resolved
 * (merged variables, sanitized HTML) — deliberately not 9 separate
 * Mailable classes per type, since they'd be identical except for
 * which subject/body they carry. Implements `ShouldQueue` via
 * being dispatched through `Mail::to(...)->queue()` (see
 * EmailNotificationService) rather than the class itself, matching the
 * task's "use queued notifications... do not send email synchronously
 * for high-volume events" requirement.
 */
class LoyaltyNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $bodyHtml,
    ) {}

    public function build(): self
    {
        return $this->subject($this->emailSubject)
            ->view('emails.loyalty-notification')
            ->with(['bodyHtml' => $this->bodyHtml]);
    }
}
