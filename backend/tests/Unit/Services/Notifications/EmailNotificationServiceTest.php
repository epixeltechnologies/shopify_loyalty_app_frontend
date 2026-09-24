<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\Customer;
use App\Models\Shop;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_queue_attempt_is_logged_as_sent(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $log = app(EmailNotificationService::class)->send($shop, $customer, 'welcome', 'ada@example.com', 'Subject', '<p>Body</p>');

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
    }

    public function test_a_failure_to_queue_is_logged_with_a_reason_and_never_thrown(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail transport unavailable'));

        $log = app(EmailNotificationService::class)->send($shop, $customer, 'welcome', 'ada@example.com', 'Subject', '<p>Body</p>');

        $this->assertSame('failed', $log->fresh()->status);
        $this->assertStringContainsString('mail transport unavailable', $log->fresh()->failure_reason);
    }

    public function test_the_log_never_stores_the_rendered_email_body(): void
    {
        Mail::fake();
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $log = app(EmailNotificationService::class)->send($shop, $customer, 'welcome', 'ada@example.com', 'Subject', '<p>Sensitive body content</p>');

        $this->assertDatabaseMissing('email_logs', ['id' => $log->id, 'failure_reason' => '<p>Sensitive body content</p>']);
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('email_logs');
        $this->assertNotContains('body', $columns);
        $this->assertNotContains('message', $columns);
    }
}
