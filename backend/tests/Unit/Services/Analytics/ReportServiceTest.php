<?php

namespace Tests\Unit\Services\Analytics;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Services\Analytics\DateRangeResolver;
use App\Services\Analytics\ReportService;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_report_is_scoped_to_the_requesting_shop_only(): void
    {
        $shopA = Shop::factory()->create(['timezone' => 'UTC']);
        $shopB = Shop::factory()->create(['timezone' => 'UTC']);
        $customerA = Customer::factory()->for($shopA)->create();
        $customerB = Customer::factory()->for($shopB)->create();
        app(PointsLedgerService::class)->post($customerA, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        app(PointsLedgerService::class)->post($customerB, PointTransaction::DIRECTION_EARN, 5000, PointTransaction::SOURCE_PURCHASE);

        [$start, $end] = app(DateRangeResolver::class)->resolve($shopA, 'today');
        $report = app(ReportService::class)->points($shopA, $start, $end);

        $this->assertSame(1000, $report['summary']['total_earned']);
    }

    public function test_outstanding_liability_reflects_current_balance_not_window_activity(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);
        $customer = Customer::factory()->for($shop)->create();
        $ledger = app(PointsLedgerService::class);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 1000, PointTransaction::SOURCE_PURCHASE);
        $ledger->post($customer, PointTransaction::DIRECTION_REDEEM, 300, PointTransaction::SOURCE_REWARD_REDEMPTION);

        [$start, $end] = app(DateRangeResolver::class)->resolve($shop, 'today');
        $report = app(ReportService::class)->points($shop, $start, $end);

        $this->assertSame(700, $report['summary']['outstanding_liability']);
    }
}
