<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\StorefrontProfileResource;
use App\Support\Tenancy\CustomerContext;
use Illuminate\Http\JsonResponse;

class StorefrontProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return (new StorefrontProfileResource(CustomerContext::customer()))->response();
    }
}
