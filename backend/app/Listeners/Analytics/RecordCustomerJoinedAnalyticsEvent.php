<?php

namespace App\Listeners\Analytics;

use App\Events\Customers\CustomerEnrolled;
use App\Services\Analytics\EventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordCustomerJoinedAnalyticsEvent implements ShouldQueue
{
    public function __construct(private readonly EventRecorder $events) {}

    public function handle(CustomerEnrolled $event): void
    {
        $this->events->record($event->customer->shop, 'customer.joined', $event->customer);
    }
}
