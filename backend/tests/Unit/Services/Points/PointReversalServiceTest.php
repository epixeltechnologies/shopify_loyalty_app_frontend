<?php

namespace Tests\Unit\Services\Points;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\Refund;
use App\Models\Shop;
use App\Services\Points\PointReversalService;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithEarnedPoints(int $earnedPoints, int $totalCents): array
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['total_cents' => $totalCents]);

        app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_EARN, $earnedPoints, PointTransaction::SOURCE_PURCHASE,
            sourceReferenceType: Order::class, sourceReferenceId: $order->id,
            idempotencyKey: "order_points:{$shop->id}:{$order->id}:1",
        );

        return [$shop, $customer, $order];
    }

    public function test_cancellation_reverses_all_earned_points(): void
    {
        [, $customer, $order] = $this->orderWithEarnedPoints(100, 10000);

        $reversal = app(PointReversalService::class)->reverseForCancellation($order->fresh());

        $this->assertSame(-100, $reversal->points);
        $this->assertSame(0, $customer->point->fresh()->balance);
    }

    public function test_cancellation_reversal_is_idempotent(): void
    {
        [, $customer, $order] = $this->orderWithEarnedPoints(100, 10000);
        $service = app(PointReversalService::class);

        $first = $service->reverseForCancellation($order->fresh());
        $second = $service->reverseForCancellation($order->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(0, $customer->point->fresh()->balance); // not -100
    }

    public function test_cancellation_with_no_customer_returns_null(): void
    {
        $shop = Shop::factory()->create();
        $order = Order::factory()->for($shop)->create(['customer_id' => null]);

        $this->assertNull(app(PointReversalService::class)->reverseForCancellation($order));
    }

    public function test_a_full_refund_reverses_all_earned_points(): void
    {
        [$shop, $customer, $order] = $this->orderWithEarnedPoints(100, 10000);
        $refund = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 10000, 'is_partial' => false]);

        $reversal = app(PointReversalService::class)->reverseForRefund($refund);

        $this->assertSame(-100, $reversal->points);
        $this->assertSame(0, $customer->point->fresh()->balance);
    }

    public function test_a_50_percent_partial_refund_reverses_half_the_points(): void
    {
        [$shop, $customer, $order] = $this->orderWithEarnedPoints(100, 10000);
        $refund = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 5000, 'is_partial' => true]);

        $reversal = app(PointReversalService::class)->reverseForRefund($refund);

        $this->assertSame(-50, $reversal->points);
        $this->assertSame(50, $customer->point->fresh()->balance);
    }

    public function test_two_sequential_partial_refunds_never_reverse_more_than_was_earned(): void
    {
        [$shop, $customer, $order] = $this->orderWithEarnedPoints(100, 10000);
        $service = app(PointReversalService::class);

        // Two 60% refunds — naively 60+60=120% would over-reverse past
        // the 100 points actually earned.
        $refund1 = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 6000, 'is_partial' => true]);
        $service->reverseForRefund($refund1);

        $refund2 = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 6000, 'is_partial' => true]);
        $reversal2 = $service->reverseForRefund($refund2);

        // First reversal: 60% of 100 = 60. Second: capped at the
        // remaining 40, not another 60.
        $this->assertSame(-40, $reversal2->points);
        $this->assertSame(0, $customer->point->fresh()->balance); // never negative from over-reversal
    }

    public function test_refund_reversal_is_idempotent_per_refund(): void
    {
        [$shop, $customer, $order] = $this->orderWithEarnedPoints(100, 10000);
        $refund = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 10000]);
        $service = app(PointReversalService::class);

        $first = $service->reverseForRefund($refund);
        $second = $service->reverseForRefund($refund->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(0, $customer->point->fresh()->balance);
    }

    public function test_refund_reversal_marks_the_refund_row_as_reversed(): void
    {
        [$shop, , $order] = $this->orderWithEarnedPoints(100, 10000);
        $refund = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 10000]);

        app(PointReversalService::class)->reverseForRefund($refund);

        $refund->refresh();
        $this->assertNotNull($refund->points_reversed_at);
        $this->assertNotNull($refund->point_transaction_id);
    }

    public function test_a_refund_when_no_points_were_ever_earned_reverses_nothing(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->for($customer)->create(['total_cents' => 10000]);
        $refund = Refund::factory()->for($shop)->for($order)->create(['amount_cents' => 10000]);

        $this->assertNull(app(PointReversalService::class)->reverseForRefund($refund));
        $this->assertSame(0, $customer->point->fresh()->balance);
    }
}
