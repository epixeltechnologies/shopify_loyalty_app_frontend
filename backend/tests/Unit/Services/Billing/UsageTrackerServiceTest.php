<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Shop;
use App\Models\SubscriptionUsage;
use App\Services\Billing\UsageTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the atomic check-and-increment (`tryIncrementIfUnderLimit`)
 * that closes the check-then-act race described in its own docblock
 * and in CustomerService::enroll()/PointRuleService::create().
 *
 * IMPORTANT SCOPE NOTE: PHPUnit runs a single PHP process against a
 * single SQLite in-memory connection (see phpunit.xml) — there is no
 * way to genuinely fire two concurrent, overlapping transactions from
 * within one test process against that harness, so this suite cannot
 * literally reproduce two requests racing on real, parallel database
 * connections. What it DOES verify, which is the part of the fix that
 * actually matters for correctness, is the boundary logic itself: that
 * exactly `limit` reservations succeed and the next one fails, cleanly,
 * every time, with no drift — the same guarantee `lockForUpdate()`
 * provides is what makes that boundary hold even when two real
 * connections do overlap in production (MySQL/InnoDB, not SQLite).
 */
class UsageTrackerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_increment_creates_the_counter_row_if_missing(): void
    {
        $shop = Shop::factory()->create();
        $service = app(UsageTrackerService::class);

        $service->increment($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS);

        $this->assertSame(1, $service->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));
    }

    public function test_try_increment_if_under_limit_succeeds_while_under_the_limit(): void
    {
        $shop = Shop::factory()->create();
        $service = app(UsageTrackerService::class);

        $result = $service->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 5);

        $this->assertTrue($result);
        $this->assertSame(1, $service->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));
    }

    public function test_try_increment_if_under_limit_fails_exactly_at_the_boundary_and_does_not_increment(): void
    {
        $shop = Shop::factory()->create();
        $service = app(UsageTrackerService::class);
        $limit = 3;

        // Fill to exactly the limit.
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($service->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, $limit));
        }

        $this->assertSame($limit, $service->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));

        // The next reservation must fail AND must not increment the counter.
        $result = $service->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, $limit);

        $this->assertFalse($result);
        $this->assertSame($limit, $service->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));
    }

    public function test_a_null_limit_always_succeeds_and_still_increments(): void
    {
        $shop = Shop::factory()->create();
        $service = app(UsageTrackerService::class);

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($service->tryIncrementIfUnderLimit($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, null));
        }

        $this->assertSame(50, $service->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));
    }

    public function test_reservations_are_scoped_per_shop_and_never_leak_into_another_shops_counter(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $service = app(UsageTrackerService::class);

        $service->tryIncrementIfUnderLimit($shopA, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 1);
        $service->tryIncrementIfUnderLimit($shopA, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 1); // fails — A is at its own limit

        $this->assertSame(1, $service->current($shopA, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));
        $this->assertSame(0, $service->current($shopB, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));

        // B has its own, independent limit — unaffected by A being maxed out.
        $this->assertTrue($service->tryIncrementIfUnderLimit($shopB, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 1));
    }

    public function test_decrement_never_goes_below_zero(): void
    {
        $shop = Shop::factory()->create();
        $service = app(UsageTrackerService::class);

        $service->increment($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS);
        $service->decrement($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS, 5); // would go negative — guarded by the `where('value', '>=', $by)` clause

        $this->assertSame(1, $service->current($shop, SubscriptionUsage::METRIC_ACTIVE_CUSTOMERS));
    }
}
