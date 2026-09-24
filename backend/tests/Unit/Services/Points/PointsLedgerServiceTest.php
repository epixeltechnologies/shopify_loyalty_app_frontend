<?php

namespace Tests\Unit\Services\Points;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_an_earn_transaction_increases_balance_and_lifetime_earned(): void
    {
        $customer = Customer::factory()->create();

        $transaction = app(PointsLedgerService::class)->post(
            customer: $customer, direction: PointTransaction::DIRECTION_EARN,
            points: 100, source: PointTransaction::SOURCE_PURCHASE,
        );

        $this->assertSame(100, $transaction->points);
        $this->assertSame(100, $transaction->balance_after);
        $this->assertSame(100, $customer->point->fresh()->balance);
        $this->assertSame(100, $customer->point->fresh()->lifetime_earned);
    }

    public function test_posting_a_redeem_transaction_decreases_balance_and_stores_a_negative_points_value(): void
    {
        $customer = Customer::factory()->create();
        $ledger = app(PointsLedgerService::class);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);

        $transaction = $ledger->post($customer, PointTransaction::DIRECTION_REDEEM, 200, PointTransaction::SOURCE_REWARD_REDEMPTION);

        $this->assertSame(-200, $transaction->points);
        $this->assertSame(300, $transaction->balance_after);
        $this->assertSame(200, $customer->point->fresh()->lifetime_redeemed);
    }

    public function test_a_manual_adjustment_routes_to_lifetime_adjusted_not_lifetime_earned(): void
    {
        // Regression coverage: the original implementation counted any
        // positive `adjust` toward lifetime_earned, conflating admin
        // corrections with genuine program earnings.
        $customer = Customer::factory()->create();

        app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_ADJUST, 250, PointTransaction::SOURCE_MANUAL_ADJUSTMENT,
        );

        $account = $customer->point->fresh();
        $this->assertSame(250, $account->balance);
        $this->assertSame(250, $account->lifetime_adjusted);
        $this->assertSame(0, $account->lifetime_earned);
    }

    public function test_a_negative_adjustment_is_stored_as_provided_without_double_negating(): void
    {
        $customer = Customer::factory()->create();
        $ledger = app(PointsLedgerService::class);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);

        $transaction = $ledger->post($customer, PointTransaction::DIRECTION_ADJUST, -100, PointTransaction::SOURCE_MANUAL_ADJUSTMENT);

        $this->assertSame(-100, $transaction->points);
        $this->assertSame(400, $transaction->balance_after);
        $this->assertSame(-100, $customer->point->fresh()->lifetime_adjusted);
    }

    public function test_last_transaction_at_is_stamped_on_every_post(): void
    {
        $customer = Customer::factory()->create();

        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 10, PointTransaction::SOURCE_PURCHASE);

        $this->assertNotNull($customer->point->fresh()->last_transaction_at);
    }

    public function test_posting_twice_with_the_same_idempotency_key_returns_the_original_transaction_and_does_not_double_post(): void
    {
        $customer = Customer::factory()->create();
        $ledger = app(PointsLedgerService::class);

        $first = $ledger->post(
            $customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE,
            idempotencyKey: 'order_points:1:42:1',
        );
        $second = $ledger->post(
            $customer, PointTransaction::DIRECTION_EARN, 999, PointTransaction::SOURCE_PURCHASE, // deliberately different amount
            idempotencyKey: 'order_points:1:42:1',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(100, $second->points); // the ORIGINAL amount, not the second call's
        $this->assertSame(100, $customer->point->fresh()->balance); // balance only reflects the first post
        $this->assertSame(1, PointTransaction::query()->where('customer_id', $customer->id)->count());
    }

    public function test_different_idempotency_keys_post_independently(): void
    {
        $customer = Customer::factory()->create();
        $ledger = app(PointsLedgerService::class);

        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 100, PointTransaction::SOURCE_PURCHASE, idempotencyKey: 'key-a');
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 50, PointTransaction::SOURCE_PURCHASE, idempotencyKey: 'key-b');

        $this->assertSame(150, $customer->point->fresh()->balance);
        $this->assertSame(2, PointTransaction::query()->where('customer_id', $customer->id)->count());
    }

    public function test_a_null_idempotency_key_never_deduplicates_against_another_null(): void
    {
        $customer = Customer::factory()->create();
        $ledger = app(PointsLedgerService::class);

        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 10, PointTransaction::SOURCE_PURCHASE);
        $ledger->post($customer, PointTransaction::DIRECTION_EARN, 10, PointTransaction::SOURCE_PURCHASE);

        $this->assertSame(20, $customer->point->fresh()->balance);
        $this->assertSame(2, PointTransaction::query()->where('customer_id', $customer->id)->count());
    }

    public function test_the_unique_constraint_is_the_backstop_when_the_in_transaction_check_is_bypassed(): void
    {
        // Directly exercises the QueryException-catch path in
        // PointsLedgerService::post() by pre-seeding a row with the
        // target idempotency key OUTSIDE the service (simulating the
        // rare cross-customer-lock race the constraint exists to catch
        // even though the in-transaction check should normally prevent
        // reaching this path at all).
        $customer = Customer::factory()->create();
        PointTransaction::query()->create([
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
            'direction' => PointTransaction::DIRECTION_EARN,
            'points' => 100,
            'balance_after' => 100,
            'source' => PointTransaction::SOURCE_PURCHASE,
            'idempotency_key' => 'preexisting-key',
        ]);

        $result = app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_EARN, 999, PointTransaction::SOURCE_PURCHASE,
            idempotencyKey: 'preexisting-key',
        );

        $this->assertSame(100, $result->points); // returns the pre-existing row, not a new/duplicate one
    }

    public function test_metadata_is_stored_and_retrievable_as_an_array(): void
    {
        $customer = Customer::factory()->create();

        $transaction = app(PointsLedgerService::class)->post(
            $customer, PointTransaction::DIRECTION_EARN, 10, PointTransaction::SOURCE_PURCHASE,
            metadata: ['rule_id' => 7, 'note' => 'test'],
        );

        $this->assertSame(['rule_id' => 7, 'note' => 'test'], $transaction->fresh()->metadata);
    }
}
