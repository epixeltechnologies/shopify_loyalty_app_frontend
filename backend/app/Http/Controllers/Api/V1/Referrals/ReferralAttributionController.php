<?php

namespace App\Http\Controllers\Api\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referrals\RecordReferralClickRequest;
use App\Services\Referrals\ReferralAttributionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Prepared for a future storefront widget — this app has no
 * customer-facing (unauthenticated visitor) auth/tenant-resolution
 * layer yet, so — exactly like CustomerRewardController in the rewards
 * engine — this sits under the same merchant-session-authenticated
 * route group for now rather than a genuinely public one. A real
 * storefront integration needs its own lightweight tenant resolution
 * (e.g. a shop-domain query param + signature/rate-limiting) that is
 * explicitly out of scope this milestone. See docs/REFERRAL_PROGRAM.md.
 */
class ReferralAttributionController extends Controller
{
    public function __construct(private readonly ReferralAttributionService $attribution) {}

    public function click(RecordReferralClickRequest $request): JsonResponse
    {
        $referral = $this->attribution->recordClick(
            shop: TenantContext::shop(),
            referralCode: $request->validated('referral_code'),
            visitorToken: $request->validated('visitor_token'),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['data' => ['attributed' => $referral !== null]]);
    }
}
