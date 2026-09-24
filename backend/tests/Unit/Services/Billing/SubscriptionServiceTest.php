<?php

namespace Tests\Unit\Services\Billing;

use App\Events\Subscription\SubscriptionActivated;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assert_active_throws_for_a_shop_with_no_subscription(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(RuntimeException::class);
        app(SubscriptionService::class)->assertActive($shop);
    }

    public function test_assert_active_passes_for_a_shop_with_an_active_subscription(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        app(SubscriptionService::class)->assertActive($shop);
        $this->addToAssertionCount(1); // did not throw
    }

    public function test_sync_from_webhook_attaches_to_the_pending_subscription_and_activates_it(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        $pending = Subscription::factory()->for($shop)->for($plan)->create([
            'status' => 'pending',
            'shopify_subscription_id' => null,
        ]);

        $updated = app(SubscriptionService::class)->syncFromWebhook($shop, [
            'app_subscription' => [
                'admin_graphql_api_id' => 'gid://shopify/AppSubscription/999',
                'name' => $plan->name,
                'status' => 'ACTIVE',
            ],
        ]);

        $this->assertSame($pending->id, $updated->id);
        $this->assertSame('active', $updated->status);
        $this->assertSame('gid://shopify/AppSubscription/999', $updated->shopify_subscription_id);

        Event::assertDispatched(SubscriptionActivated::class);
        $this->assertDatabaseHas('subscription_events', [
            'shop_id' => $shop->id,
            'from_status' => 'pending',
            'to_status' => 'active',
            'trigger' => 'shopify_webhook',
        ]);
    }

    public function test_sync_from_webhook_does_not_fire_activated_for_a_non_activating_transition(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create([
            'status' => 'active',
            'shopify_subscription_id' => 'gid://shopify/AppSubscription/123',
        ]);

        app(SubscriptionService::class)->syncFromWebhook($shop, [
            'app_subscription' => [
                'admin_graphql_api_id' => 'gid://shopify/AppSubscription/123',
                'status' => 'FROZEN',
            ],
        ]);

        Event::assertNotDispatched(SubscriptionActivated::class);
        $this->assertDatabaseHas('subscriptions', ['shop_id' => $shop->id, 'status' => 'frozen']);
    }

    public function test_sync_from_webhook_records_an_event_for_every_transition(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create([
            'status' => 'active',
            'shopify_subscription_id' => 'gid://shopify/AppSubscription/555',
        ]);

        app(SubscriptionService::class)->syncFromWebhook($shop, [
            'app_subscription' => ['admin_graphql_api_id' => 'gid://shopify/AppSubscription/555', 'status' => 'CANCELLED'],
        ]);

        $event = SubscriptionEvent::query()->where('shop_id', $shop->id)->first();
        $this->assertSame('active', $event->from_status);
        $this->assertSame('cancelled', $event->to_status);

        $this->assertNotNull(Subscription::query()->where('shop_id', $shop->id)->first()->cancelled_at);
    }
}
