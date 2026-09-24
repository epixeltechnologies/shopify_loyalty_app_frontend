<?php

namespace App\Http\Controllers\Api\V1\Points;

use App\Http\Controllers\Controller;
use App\Services\Points\PointTransactionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/** Shop-wide point statistics for the merchant admin dashboard — see PointTransactionService::statisticsFor(). */
class PointStatisticsController extends Controller
{
    public function __construct(private readonly PointTransactionService $transactions) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->transactions->statisticsFor(TenantContext::shop())]);
    }
}
