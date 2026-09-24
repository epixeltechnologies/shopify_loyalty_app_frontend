<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\EnrollCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Customers\CustomerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customers->paginateForShop(TenantContext::shop(), filters: $request->only(['search', 'status', 'vip_tier_id', 'sort']));

        return CustomerResource::collection($customers)->response();
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return (new CustomerResource($customer->load('vipTier')))->response();
    }

    public function store(EnrollCustomerRequest $request): JsonResponse
    {
        $customer = $this->service->enroll(TenantContext::shop(), $request->validated());

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }
}
