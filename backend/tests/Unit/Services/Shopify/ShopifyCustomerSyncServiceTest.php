<?php

namespace Tests\Unit\Services\Shopify;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Shopify\ShopifyCustomerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyCustomerSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_from_create_does_not_auto_enroll(): void
    {
        $shop = Shop::factory()->create();
        $service = app(ShopifyCustomerSyncService::class);

        $result = $service->syncFromCreate($shop, ['id' => 999, 'email' => 'new@example.com']);

        $this->assertNull($result);
        $this->assertSame(0, Customer::query()->where('shop_id', $shop->id)->count());
    }

    public function test_sync_from_create_stamps_last_synced_at_if_already_enrolled(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '555', 'last_synced_at' => null]);

        app(ShopifyCustomerSyncService::class)->syncFromCreate($shop, ['id' => 555]);

        $this->assertNotNull($customer->fresh()->last_synced_at);
    }

    public function test_sync_from_update_syncs_identity_fields_for_an_enrolled_customer(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '555', 'email' => 'old@example.com']);

        app(ShopifyCustomerSyncService::class)->syncFromUpdate($shop, [
            'id' => 555, 'email' => 'new@example.com', 'first_name' => 'Updated',
        ]);

        $customer->refresh();
        $this->assertSame('new@example.com', $customer->email);
        $this->assertSame('Updated', $customer->first_name);
    }

    public function test_sync_from_update_is_a_no_op_for_an_unenrolled_customer(): void
    {
        $shop = Shop::factory()->create();

        app(ShopifyCustomerSyncService::class)->syncFromUpdate($shop, ['id' => 999, 'email' => 'ghost@example.com']);

        $this->assertSame(0, Customer::query()->where('shop_id', $shop->id)->count());
    }

    public function test_sync_from_delete_suspends_rather_than_deletes(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['shopify_customer_id' => '555', 'status' => 'active']);

        app(ShopifyCustomerSyncService::class)->syncFromDelete($shop, ['id' => 555]);

        $customer->refresh();
        $this->assertSame('suspended', $customer->status);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]); // row preserved
    }

    public function test_find_or_enroll_creates_a_new_customer_within_plan_limits(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $customer = app(ShopifyCustomerSyncService::class)->findOrEnroll($shop, ['id' => 42, 'email' => 'a@example.com']);

        $this->assertNotNull($customer);
        $this->assertSame('42', $customer->shopify_customer_id);
        $this->assertNotNull($customer->last_synced_at);
    }

    public function test_find_or_enroll_returns_null_and_does_not_throw_when_the_plan_limit_is_reached(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create(['max_active_customers' => 1]);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);
        Customer::factory()->for($shop)->create(); // fills the only slot

        $result = app(ShopifyCustomerSyncService::class)->findOrEnroll($shop, ['id' => 99, 'email' => 'overflow@example.com']);

        $this->assertNull($result);
    }

    public function test_find_or_enroll_is_idempotent_for_an_already_enrolled_customer(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);
        $existing = Customer::factory()->for($shop)->create(['shopify_customer_id' => '42']);

        $result = app(ShopifyCustomerSyncService::class)->findOrEnroll($shop, ['id' => 42]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(1, Customer::query()->where('shop_id', $shop->id)->count());
    }
}
