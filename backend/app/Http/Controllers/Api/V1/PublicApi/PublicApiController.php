<?php

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Reserved namespace for external-integration endpoints — Professional
 * only (`feature:api.full_access`, see routes/api.php). `ping()` is
 * deliberately the only endpoint here today: it exists to prove the
 * `api.full_access` entitlement gate end-to-end (a real route a
 * Professional shop can reach and a Starter shop is rejected from) without
 * building out full public API surface area ahead of an actual external
 * integration requirement — see docs/NEXT_STEPS.md.
 */
class PublicApiController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['data' => [
            'shop' => TenantContext::shop()->shopify_domain,
            'status' => 'ok',
        ]]);
    }
}
