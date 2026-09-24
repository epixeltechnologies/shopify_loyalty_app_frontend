<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\NotificationSetting;
use App\Models\Shop;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_disabled_notification_type_sends_nothing(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        NotificationSetting::query()->create(['shop_id' => $shop->id, 'type' => 'welcome', 'enabled' => false]);

        app(NotificationService::class)->notify($shop, $customer, 'welcome');

        Mail::assertNothingQueued();
        $this->assertSame(0, EmailLog::query()->count());
    }

    public function test_an_enabled_notification_queues_an_email_and_logs_it(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['email' => 'ada@example.com']);

        app(NotificationService::class)->notify($shop, $customer, 'welcome');

        Mail::assertQueued(\App\Mail\LoyaltyNotificationMail::class);
        $this->assertDatabaseHas('email_logs', ['shop_id' => $shop->id, 'notification_type' => 'welcome', 'recipient' => 'ada@example.com', 'status' => 'sent']);
    }

    public function test_a_customer_with_no_email_is_never_sent_to(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['email' => null]);

        app(NotificationService::class)->notify($shop, $customer, 'welcome');

        Mail::assertNothingQueued();
    }

    public function test_variables_are_substituted_into_the_default_template(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['first_name' => 'Ada']);

        app(NotificationService::class)->notify($shop, $customer, 'points_earned', ['points' => '250', 'balance' => '1250']);

        Mail::assertQueued(\App\Mail\LoyaltyNotificationMail::class, function ($mail) {
            return str_contains($mail->bodyHtml, '250') && str_contains($mail->bodyHtml, '1250') && str_contains($mail->bodyHtml, 'Ada');
        });
    }

    public function test_a_merchant_customized_template_overrides_the_default(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['first_name' => 'Ada']);
        NotificationSetting::query()->create([
            'shop_id' => $shop->id, 'type' => 'welcome', 'enabled' => true,
            'subject' => 'Custom subject for {{customer_name}}', 'message' => '<p>Custom message.</p>',
        ]);

        app(NotificationService::class)->notify($shop, $customer, 'welcome');

        Mail::assertQueued(\App\Mail\LoyaltyNotificationMail::class, function ($mail) {
            return $mail->emailSubject === 'Custom subject for Ada' && $mail->bodyHtml === '<p>Custom message.</p>';
        });
    }

    public function test_preview_renders_with_sample_data_and_sends_nothing(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();

        $preview = app(NotificationService::class)->preview($shop, 'points_earned');

        $this->assertStringContainsString('Alex', $preview['message']);
        Mail::assertNothingQueued();
        $this->assertSame(0, EmailLog::query()->count());
    }

    public function test_preview_sanitizes_a_custom_message_before_rendering(): void
    {
        $shop = Shop::factory()->create();

        $preview = app(NotificationService::class)->preview($shop, 'welcome', 'Hi', '<p onclick="evil()">Test</p>');

        $this->assertStringNotContainsString('onclick', $preview['message']);
    }
}
