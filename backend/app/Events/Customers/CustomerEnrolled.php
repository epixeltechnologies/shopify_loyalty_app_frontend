<?php

namespace App\Events\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerEnrolled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Customer $customer) {}
}
