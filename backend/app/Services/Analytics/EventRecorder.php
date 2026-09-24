<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\Shop;

/**
 * ANALYTICS: writes to the raw `analytics_events` feed — the write path
 * described in that migration's comment. Call this from domain services
 * at the point an event happens (e.g. CustomerService::enroll(),
 * PointsLedgerService::post()) rather than reconstructing events later
 * from other tables; a direct write here is cheap and keeps the feed
 * complete even for event types that don't have a natural home in any
 * other table.
 */
class EventRecorder
{
    public function record(Shop $shop, string $eventType, ?Customer $customer = null, array $properties = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'shop_id' => $shop->id,
            'customer_id' => $customer?->id,
            'event_type' => $eventType,
            'properties' => $properties,
        ]);
    }
}
