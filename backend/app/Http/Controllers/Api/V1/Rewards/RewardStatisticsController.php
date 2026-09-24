<?php

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\RewardRedemptionRepositoryInterface;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/** Shop-wide reward/redemption statistics for the merchant admin dashboard. */
class RewardStatisticsController extends Controller
{
    public function __construct(private readonly RewardRedemptionRepositoryInterface $redemptions) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->redemptions->statisticsFor(TenantContext::shop())]);
    }
}
