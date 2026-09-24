<?php

namespace Tests\Unit\Services\Points;

use App\Exceptions\Points\NegativeBalanceNotAllowedException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Services\Points\PointAdjustmentService;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_positive_adjustment_always_succeeds(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $transaction = app(PointAdjustmentService::class)->adjust($shop, $customer, 100, 'Customer service gesture', 'staff@example.com');

        $this->assertSame(100, $customer->point->fresh()->balance);
        $this->assertSame(PointTransaction::SOURCE_MANUAL_ADJUSTMENT, $transaction->source);
    }

    public function test_a_negative_adjustment_within_balance_succeeds(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 500, PointTransaction::SOURCE_PURCHASE);

        app(PointAdjustmentService::class)->adjust($shop, $customer, -200, 'Correction', 'staff@example.com');

        $this->assertSame(300, $customer->point->fresh()->balance);
    }

    public function test_a_negative_adjustment_that_would_go_negative_is_blocked_by_default(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 50, PointTransaction::SOURCE_PURCHASE);

        $this->expectException(NegativeBalanceNotAllowedException::class);
        app(PointAdjustmentService::class)->adjust($shop, $customer, -100, 'Oops', 'staff@example.com');
    }

    public function test_the_blocked_adjustment_does_not_change_the_balance_at_all(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 50, PointTransaction::SOURCE_PURCHASE);

        try {
            app(PointAdjustmentService::class)->adjust($shop, $customer, -100, 'Oops', 'staff@example.com');
        } catch (NegativeBalanceNotAllowedException) {
            // expected
        }

        $this->assertSame(50, $customer->point->fresh()->balance);
    }

    public function test_a_shop_can_opt_in_to_allowing_a_negative_balance(): void
    {
        $shop = Shop::factory()->create();
        ShopSetting::factory()->for($shop)->create(['allow_negative_point_balance' => true]);
        $customer = Customer::factory()->for($shop)->create();
        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 50, PointTransaction::SOURCE_PURCHASE);

        app(PointAdjustmentService::class)->adjust($shop, $customer, -100, 'Correction', 'staff@example.com');

        $this->assertSame(-50, $customer->point->fresh()->balance);
    }

    public function test_every_adjustment_is_audited(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        app(PointAdjustmentService::class)->adjust($shop, $customer, 100, 'Goodwill gesture', 'staff@example.com');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'points.manual_adjustment',
            'auditable_id' => $customer->id,
        ]);
        $audit = AuditLog::query()->where('action', 'points.manual_adjustment')->first();
        $this->assertSame('Goodwill gesture', $audit->changes['reason']);
        $this->assertSame('staff@example.com', $audit->changes['admin_identity']);
    }

    public function test_correct_uses_a_distinct_source_and_actor_type_from_adjust(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $transaction = app(PointAdjustmentService::class)->correct($shop, $customer, 50, 'Data migration reconciliation');

        $this->assertSame(PointTransaction::SOURCE_ADMINISTRATIVE_CORRECTION, $transaction->source);
        $this->assertDatabaseHas('audit_logs', ['action' => 'points.administrative_correction', 'actor_type' => 'system']);
    }
}
