<?php

namespace Tests\Feature\Settings;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_nine_notification_types(): void
    {
        $shop = Shop::factory()->create();

        $response = $this->actingAsShop($shop)->getJson('/api/v1/notification-settings')->assertOk();

        $this->assertCount(9, $response->json('data'));
    }

    public function test_updating_a_template_sanitizes_the_message(): void
    {
        $shop = Shop::factory()->create();

        $response = $this->actingAsShop($shop)
            ->patchJson('/api/v1/notification-settings/welcome', ['message' => '<p onclick="evil()">Hi there</p>'])
            ->assertOk();

        $this->assertStringNotContainsString('onclick', $response->json('data.message'));
    }

    public function test_an_unknown_notification_type_is_rejected(): void
    {
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->patchJson('/api/v1/notification-settings/not_a_real_type', ['enabled' => false])
            ->assertStatus(422);
    }

    public function test_preview_never_creates_an_email_log_or_sends_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $shop = Shop::factory()->create();

        $this->actingAsShop($shop)
            ->postJson('/api/v1/notification-settings/welcome/preview', ['message' => '<p>Hello {{customer_name}}</p>'])
            ->assertOk();

        \Illuminate\Support\Facades\Mail::assertNothingQueued();
        $this->assertSame(0, \App\Models\EmailLog::query()->count());
    }

    public function test_notification_settings_are_isolated_per_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $this->actingAsShop($shopA)->patchJson('/api/v1/notification-settings/welcome', ['enabled' => false])->assertOk();
        $response = $this->actingAsShop($shopB)->getJson('/api/v1/notification-settings/welcome')->assertOk();

        $this->assertTrue($response->json('data.enabled')); // shop B's own row is untouched, still defaults enabled
    }
}
