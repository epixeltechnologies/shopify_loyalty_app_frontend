<?php

namespace Tests\Unit\Listeners\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Services\Points\PointsLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordPointsAnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_earn_transaction_records_a_points_earned_event_without_pii(): void
    {
        $customer = Customer::factory()->create(['email' => 'shouldnotappear@example.com']);

        app(PointsLedgerService::class)->post($customer, PointTransaction::DIRECTION_EARN, 250, PointTransaction::SOURCE_PURCHASE);

        $this->assertDatabaseHas('analytics_events', [
            'shop_id' => $customer->shop_id, 'customer_id' => $customer->id, 'event_type' => 'points.earned',
        ]);

        $event = AnalyticsEvent::query()->where('event_type', 'points.earned')->first();
        $this->assertSame(250, $event->properties['points']);
        $this->assertStringNotContainsString('shouldnotappear', json_encode($event->properties));
    }
}
