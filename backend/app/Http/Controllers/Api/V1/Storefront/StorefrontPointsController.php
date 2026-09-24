<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\PointTransactionResource;
use App\Services\Points\PointAccountService;
use App\Services\Points\PointTransactionService;
use App\Support\Tenancy\CustomerContext;
use Illuminate\Http\JsonResponse;

/** Reuses PointAccountService/PointTransactionService directly — no separate storefront points calculation. */
class StorefrontPointsController extends Controller
{
    public function __construct(
        private readonly PointAccountService $accounts,
        private readonly PointTransactionService $transactions,
    ) {}

    public function balance(): JsonResponse
    {
        return response()->json(['data' => $this->accounts->summary(CustomerContext::customer())]);
    }

    /** Paginated — never the full history in one response, per the task's performance requirement. */
    public function history(): JsonResponse
    {
        return PointTransactionResource::collection(
            $this->transactions->historyFor(CustomerContext::customer(), perPage: 20)
        )->response();
    }
}
