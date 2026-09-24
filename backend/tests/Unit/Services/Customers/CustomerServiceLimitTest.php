<?php

namespace Tests\Unit\Services\Customers;

use App\Exceptions\Billing\LimitReachedException;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Customers\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_succeeds_within_the_plan_limit(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $customer = app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '1', 'email' => 'a@example.com']);

        $this->assertNotNull($customer->id);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_enrollment_throws_limit_reached_at_the_boundary(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create(['max_active_customers' => 2]);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '1']);
        app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '2']);

        $this->expectException(LimitReachedException::class);
        app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '3']);
    }

    public function test_a_rejected_enrollment_creates_no_customer_row_at_all(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create(['max_active_customers' => 1]);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '1']);

        try {
            app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '2']);
        } catch (LimitReachedException) {
            // expected
        }

        // The failed attempt's transaction rolled back entirely — no
        // partial customer row, no partial Point row, no stray usage
        // increment left behind.
        $this->assertSame(1, Customer::query()->where('shop_id', $shop->id)->count());
        $this->assertDatabaseMissing('customers', ['shopify_customer_id' => '2']);
    }

    public function test_the_limit_reached_exception_carries_the_correct_details(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create(['max_active_customers' => 1]);
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '1']);

        try {
            app(CustomerService::class)->enroll($shop, ['shopify_customer_id' => '2']);
            $this->fail('Expected LimitReachedException.');
        } catch (LimitReachedException $e) {
            $this->assertSame('active_customers', $e->feature);
            $this->assertSame(1, $e->limit);
            $this->assertSame('professional', $e->requiredPlan);
        }
    }

    public function test_find_or_enroll_from_shopify_is_idempotent_for_an_already_enrolled_customer(): void
    {
        $shop = Shop::factory()->create();
        $plan = Plan::factory()->starter()->create();
        Subscription::factory()->for($shop)->for($plan)->create(['status' => 'active']);

        $service = app(CustomerService::class);
        $first = $service->findOrEnrollFromShopify($shop, ['id' => '42', 'email' => 'a@example.com']);
        $second = $service->findOrEnrollFromShopify($shop, ['id' => '42', 'email' => 'a@example.com']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Customer::query()->where('shop_id', $shop->id)->count());
    }
}
