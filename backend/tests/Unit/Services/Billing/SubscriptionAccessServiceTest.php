<?php

namespace Tests\Unit\Services\Billing;

use App\Exceptions\Billing\ShopInactiveException;
use App\Exceptions\Billing\SubscriptionRequiredException;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_installed_shop_with_no_subscription_has_no_full_access(): void
    {
        $shop = Shop::factory()->create();
        $service = app(SubscriptionAccessService::class);

        $this->assertTrue($service->isShopActive($shop));
        $this->assertFalse($service->hasActiveSubscription($shop));
        $this->assertFalse($service->hasFullAccess($shop));
    }

    public function test_a_subscribed_installed_shop_has_full_access(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $this->assertTrue(app(SubscriptionAccessService::class)->hasFullAccess($shop));
    }

    public function test_an_uninstalled_shop_never_has_full_access_even_with_a_subscription(): void
    {
        $shop = Shop::factory()->uninstalled()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $service = app(SubscriptionAccessService::class);

        $this->assertFalse($service->isShopActive($shop));
        $this->assertFalse($service->hasFullAccess($shop));
    }

    public function test_assert_full_access_throws_shop_inactive_before_checking_subscription(): void
    {
        $shop = Shop::factory()->uninstalled()->create();

        $this->expectException(ShopInactiveException::class);
        app(SubscriptionAccessService::class)->assertFullAccess($shop);
    }

    public function test_assert_full_access_throws_subscription_required_for_an_active_shop_with_no_plan(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(SubscriptionRequiredException::class);
        app(SubscriptionAccessService::class)->assertFullAccess($shop);
    }

    public function test_assert_full_access_passes_silently_when_everything_is_in_order(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        app(SubscriptionAccessService::class)->assertFullAccess($shop);
        $this->addToAssertionCount(1); // did not throw
    }
}
