<?php

namespace Tests\Unit\Services\Analytics;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Services\Analytics\SnapshotBuilder;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_issued_matches_the_ledgers_own_total_for_the_day(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);

        app(SnapshotBuilder::class)->buildForShop($shop, now());

        $this->assertDatabaseHas('analytics_daily_snapshots', ['shop_id' => $shop->id, 'metric' => 'points_issued', 'value' => 500]);
    }

    public function test_points_redeemed_and_expired_are_stored_as_positive_magnitudes(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        $ledger = app(PointsLedgerService::class);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $ledger->post($customer, PointTransaction::DIRECTION_REDEEM, 200, PointTransaction::SOURCE_REWARD_REDEMPTION);
        $ledger->post($customer, PointTransaction::DIRECTION_EXPIRE, 100, PointTransaction::SOURCE_EXPIRATION);

        app(SnapshotBuilder::class)->buildForShop($shop, now());

        $this->assertDatabaseHas('analytics_daily_snapshots', ['shop_id' => $shop->id, 'metric' => 'points_redeemed', 'value' => 200]);
        $this->assertDatabaseHas('analytics_daily_snapshots', ['shop_id' => $shop->id, 'metric' => 'points_expired', 'value' => 100]);
    }

    public function test_activity_outside_the_requested_day_is_excluded(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        $ledger = app(PointsLedgerService::class);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);
        PointTransaction::query()->update(['created_at' => now()->subDays(5)]); // simulate yesterday's-and-older activity

        app(SnapshotBuilder::class)->buildForShop($shop, now());

        $this->assertDatabaseHas('analytics_daily_snapshots', ['shop_id' => $shop->id, 'metric' => 'points_issued', 'value' => 0]);
    }

    public function test_snapshots_are_scoped_per_shop(): void
    {
        $shopA = Shop::factory()->create(['timezone' => 'UTC']);
        $shopB = Shop::factory()->create(['timezone' => 'UTC']);
        $customerA = Customer::factory()->for($shopA)->create();
        app(PointsLedgerService::class)->post($customerA, PointTransaction::DIRECTION_EARN, 700, PointTransaction::SOURCE_PURCHASE);

        app(SnapshotBuilder::class)->buildForShop($shopB, now());

        $this->assertDatabaseHas('analytics_daily_snapshots', ['shop_id' => $shopB->id, 'metric' => 'points_issued', 'value' => 0]);
        $this->assertDatabaseMissing('analytics_daily_snapshots', ['shop_id' => $shopA->id]);
    }

    public function test_new_customers_counts_enrollments_within_the_day(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        Customer::factory()->for($shop)->create(['enrolled_at' => now()]);
        Customer::factory()->for($shop)->create(['enrolled_at' => now()->subDays(10)]);

        app(SnapshotBuilder::class)->buildForShop($shop, now());

        $this->assertDatabaseHas('analytics_daily_snapshots', ['shop_id' => $shop->id, 'metric' => 'new_customers', 'value' => 1]);
    }

    public function test_rerunning_for_the_same_day_upserts_rather_than_duplicates(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $builder = app(SnapshotBuilder::class);

        $builder->buildForShop($shop, now());
        $builder->buildForShop($shop, now());

        $this->assertSame(1, \App\Models\AnalyticsDailySnapshot::query()->where('shop_id', $shop->id)->where('metric', 'points_issued')->count());
    }
}
