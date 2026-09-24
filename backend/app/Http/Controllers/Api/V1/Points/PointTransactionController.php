<?php

namespace App\Http\Controllers\Api\V1\Points;

use App\Http\Controllers\Controller;
use App\Http\Requests\Points\AdjustPointsRequest;
use App\Http\Resources\PointTransactionResource;
use App\Models\Customer;
use App\Repositories\Contracts\PointTransactionRepositoryInterface;
use App\Services\Points\PointAccountService;
use App\Services\Points\PointAdjustmentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class PointTransactionController extends Controller
{
    public function __construct(
        private readonly PointTransactionRepositoryInterface $transactions,
        private readonly PointAccountService $accounts,
        private readonly PointAdjustmentService $adjustments,
    ) {}

    public function index(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return PointTransactionResource::collection(
            $this->transactions->paginateForCustomer($customer)
        )->response();
    }

    /** Current balance + lifetime stats — see PointAccountService::summary(). */
    public function balance(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(['data' => $this->accounts->summary($customer)]);
    }

    /**
     * Manual points adjustment by shop staff — always audited (see
     * PointAdjustmentService). Delegates the negative-balance guard and
     * audit trail to the service rather than posting to the ledger
     * directly, so this controller stays a thin HTTP adapter.
     */
    public function adjust(AdjustPointsRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('adjust', $customer);

        $transaction = $this->adjustments->adjust(
            shop: TenantContext::shop(),
            customer: $customer,
            points: $request->validated('points'),
            reason: $request->validated('note'),
            adminIdentity: $request->validated('admin_identity') ?? 'shop-session',
        );

        return (new PointTransactionResource($transaction))->response()->setStatusCode(201);
    }
}
