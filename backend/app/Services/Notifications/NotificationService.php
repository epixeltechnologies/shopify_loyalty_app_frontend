<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\NotificationSetting;
use App\Models\Shop;

/**
 * NOTIFICATIONS: the entry point every domain listener calls — "notify
 * this customer about this event, with this context" — without any
 * caller needing to know about templates, sanitization, enabled/
 * disabled state, or how email actually gets sent. Checks
 * `NotificationSetting.enabled` first (a disabled notification type
 * sends nothing, cheaply, before any rendering happens), merges
 * `$variables` into whichever template applies (the merchant's own
 * customization if they've set one, DEFAULT_TEMPLATES otherwise), and
 * hands off to EmailNotificationService.
 */
class NotificationService
{
    /**
     * Built-in fallback copy — used whenever a merchant hasn't
     * customized a notification type's subject/message. Deliberately
     * plain, safe text (already conforms to the same allow-list
     * sanitization real merchant input goes through, so there's no
     * separate "trusted" content path).
     *
     * @var array<string, array{subject: string, message: string}>
     */
    private const DEFAULT_TEMPLATES = [
        NotificationSetting::TYPE_WELCOME => ['subject' => 'Welcome to {{program_name}}!', 'message' => '<p>Hi {{customer_name}}, welcome to {{program_name}}! Start earning points on every purchase.</p>'],
        NotificationSetting::TYPE_POINTS_EARNED => ['subject' => "You've earned points!", 'message' => '<p>Hi {{customer_name}}, you just earned {{points}} points. Your new balance is {{balance}}.</p>'],
        NotificationSetting::TYPE_REWARD_REDEEMED => ['subject' => 'Your reward is ready', 'message' => '<p>Hi {{customer_name}}, your reward "{{reward_name}}" has been redeemed. Your discount code is {{discount_code}}.</p>'],
        NotificationSetting::TYPE_BIRTHDAY_REWARD => ['subject' => 'Happy birthday from {{program_name}}!', 'message' => '<p>Happy birthday, {{customer_name}}! We\'ve added {{points}} bonus points to your account.</p>'],
        NotificationSetting::TYPE_REFERRAL_REWARD => ['subject' => 'Your referral reward has arrived', 'message' => '<p>Hi {{customer_name}}, thanks for spreading the word! You\'ve earned {{points}} points for your referral.</p>'],
        NotificationSetting::TYPE_VIP_UPGRADED => ['subject' => "You've been upgraded!", 'message' => '<p>Congratulations {{customer_name}}, you\'re now a {{tier_name}} member!</p>'],
        NotificationSetting::TYPE_VIP_DOWNGRADED => ['subject' => 'Your VIP tier has changed', 'message' => '<p>Hi {{customer_name}}, your membership tier is now {{tier_name}}.</p>'],
        NotificationSetting::TYPE_POINTS_EXPIRING => ['subject' => 'Your points are expiring soon', 'message' => '<p>Hi {{customer_name}}, {{points}} points are expiring on {{expires_at}}. Redeem them before they\'re gone!</p>'],
        NotificationSetting::TYPE_REWARD_EXPIRING => ['subject' => 'A reward is expiring soon', 'message' => '<p>Hi {{customer_name}}, "{{reward_name}}" is only available for a limited time longer.</p>'],
    ];

    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly EmailNotificationService $email,
    ) {}

    /** @param  array<string, string>  $variables */
    public function notify(Shop $shop, Customer $customer, string $type, array $variables = []): void
    {
        if (! $customer->email) {
            return; // nothing to send to
        }

        $setting = $this->templates->get($shop, $type);
        if (! $setting->enabled) {
            return;
        }

        $default = self::DEFAULT_TEMPLATES[$type] ?? ['subject' => $type, 'message' => ''];
        $variables = array_merge(['program_name' => $shop->setting?->program_name ?: $shop->name, 'customer_name' => $customer->first_name ?: 'there'], $variables);

        $subject = $this->render($setting->subject ?: $default['subject'], $variables);
        $message = $this->render($setting->message ?: $default['message'], $variables);

        $this->email->send($shop, $customer, $type, $customer->email, $subject, $message);
    }

    /**
     * Renders a preview WITHOUT sending anything — the "preview
     * notification" API requirement. Uses representative sample data
     * for every variable a real send would fill in, so a merchant can
     * see exactly what their customization will look like.
     */
    public function preview(Shop $shop, string $type, ?string $customSubject = null, ?string $customMessage = null): array
    {
        $default = self::DEFAULT_TEMPLATES[$type] ?? ['subject' => $type, 'message' => ''];
        $sampleVariables = [
            'program_name' => $shop->setting?->program_name ?: $shop->name,
            'customer_name' => 'Alex',
            'points' => '250',
            'balance' => '1,250',
            'reward_name' => 'Sample Reward',
            'discount_code' => 'LOY-SAMPLE1',
            'tier_name' => 'Gold',
            'expires_at' => now()->addDays(7)->toFormattedDateString(),
        ];

        return [
            'subject' => $this->render($customSubject ?: $default['subject'], $sampleVariables),
            'message' => $this->render($this->templates->sanitize($customMessage ?: $default['message']), $sampleVariables),
        ];
    }

    private function render(string $template, array $variables): string
    {
        return strtr($template, collect($variables)->mapWithKeys(fn ($value, $key) => ["{{{$key}}}" => $value])->all());
    }
}
