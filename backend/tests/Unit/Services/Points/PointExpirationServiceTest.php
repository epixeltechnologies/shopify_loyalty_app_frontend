<?php

namespace Tests\Unit\Services\Points;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Services\Points\PointExpirationService;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointExpirationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_earn_transactions_are_reversed(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE,
            expiresAt: now()->subDay(),
        );

        $processed = app(PointExpirationService::class)->expireForShop($shop);

        $this->assertSame(1, $processed);
        $this->assertSame(0, $customer->point->fresh()->balance);
        $this->assertDatabaseHas('point_transactions', ['customer_id' => $customer->id, 'direction' => 'expire']);
    }

    public function test_not_yet_expired_points_are_left_alone(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE,
            expiresAt: now()->addDays(30),
        );

        $processed = app(PointExpirationService::class)->expireForShop($shop);

        $this->assertSame(0, $processed);
        $this->assertSame(100, $customer->point->fresh()->balance);
    }

    public function test_points_with_no_expiry_date_are_never_expired(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE); // no expiresAt

        $processed = app(PointExpirationService::class)->expireForShop($shop);

        $this->assertSame(0, $processed);
        $this->assertSame(100, $customer->point->fresh()->balance);
    }

    public function test_running_expiry_twice_never_double_expires_the_same_earn_row(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE,
            expiresAt: now()->subDay(),
        );

        $service = app(PointExpirationService::class);
        $service->expireForShop($shop);
        $secondRunProcessed = $service->expireForShop($shop);

        $this->assertSame(0, $secondRunProcessed);
        $this->assertSame(0, $customer->point->fresh()->balance); // not -100
    }

    public function test_expiry_is_scoped_per_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $customerA = Customer::factory()->for($shopA)->create();
        $customerB = Customer::factory()->for($shopB)->create();
        app(PointsLedgerService::class)->post($customerA, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE, expiresAt: now()->subDay());
        app(PointsLedgerService::class)->post($customerB, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE, expiresAt: now()->subDay());

        app(PointExpirationService::class)->expireForShop($shopA);

        $this->assertSame(0, $customerA->point->fresh()->balance);
        $this->assertSame(100, $customerB->point->fresh()->balance); // untouched
    }

    public function test_multiple_expired_rows_for_the_same_customer_all_expire(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $ledger = app(PointsLedgerService::class);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 50, PointTransaction::SOURCE_PURCHASE, expiresAt: now()->subDays(2));
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 30, PointTransaction::SOURCE_PURCHASE, expiresAt: now()->subDay());

        $processed = app(PointExpirationService::class)->expireForShop($shop);

        $this->assertSame(2, $processed);
        $this->assertSame(0, $customer->point->fresh()->balance);
    }
}
