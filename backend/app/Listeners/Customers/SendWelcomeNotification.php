<?php

namespace App\Listeners\Customers;

use App\Events\Customers\CustomerEnrolled;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotification implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CustomerEnrolled $event): void
    {
        $this->notifications->notify($event->customer->shop, $event->customer, 'welcome');
    }
}
