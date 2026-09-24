<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcilePointBalancesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_no_discrepancy_when_cached_balance_matches_ledger_sum(): void
    {
        $shop = Shop::factory()->create(['is_installed' => true]);
        $customer = Customer::factory()->for($shop)->create();

        PointTransaction::factory()->for($shop)->for($customer)->create(['direction' => 'earn', 'points' => 100, 'balance_after' => 100]);
        Point::factory()->for($shop)->for($customer)->create(['balance' => 100]);

        $this->artisan('points:reconcile', ['--shop' => $shop->shopify_domain])
            ->assertExitCode(0);
    }

    public function test_detects_a_discrepancy_without_modifying_anything(): void
    {
        $shop = Shop::factory()->create(['is_installed' => true]);
        $customer = Customer::factory()->for($shop)->create();

        PointTransaction::factory()->for($shop)->for($customer)->create(['direction' => 'earn', 'points' => 100, 'balance_after' => 100]);
        Point::factory()->for($shop)->for($customer)->create(['balance' => 150]); // deliberately wrong

        $this->artisan('points:reconcile', ['--shop' => $shop->shopify_domain])
            ->assertExitCode(1);

        // Reporting-only run must never touch the cached balance.
        $this->assertEquals(150, Point::query()->where('customer_id', $customer->id)->value('balance'));
    }

    public function test_repair_with_force_posts_a_compensating_transaction_and_never_edits_history(): void
    {
        $shop = Shop::factory()->create(['is_installed' => true]);
        $customer = Customer::factory()->for($shop)->create();

        $original = PointTransaction::factory()->for($shop)->for($customer)->create(['direction' => 'earn', 'points' => 100, 'balance_after' => 100]);
        Point::factory()->for($shop)->for($customer)->create(['balance' => 150]);

        $this->artisan('points:reconcile', ['--shop' => $shop->shopify_domain, '--repair' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('point_transactions', ['id' => $original->id, 'points' => 100]); // untouched
        $this->assertEquals(
            PointTransaction::query()->where('customer_id', $customer->id)->sum('points'),
            Point::query()->where('customer_id', $customer->id)->value('balance')
        );
    }
}
