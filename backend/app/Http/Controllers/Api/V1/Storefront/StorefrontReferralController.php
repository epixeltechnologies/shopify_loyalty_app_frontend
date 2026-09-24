<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicReferralResource;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Support\Tenancy\CustomerContext;
use Illuminate\Http\JsonResponse;

/**
 * Reuses the SAME referral code already generated at enrollment
 * (App\Services\Referrals\ReferralCodeGenerator, via CustomerService::enroll())
 * and the same ReferralRepository the admin-facing referral endpoints
 * use — never a second referral-link-generation implementation. Fraud
 * status/reasons are never exposed here (PublicReferralResource), per
 * the task's explicit "do not expose fraud detection details."
 */
class StorefrontReferralController extends Controller
{
    public function __construct(private readonly ReferralRepositoryInterface $referrals) {}

    public function code(): JsonResponse
    {
        return response()->json(['data' => ['referral_code' => CustomerContext::customer()->referral_code]]);
    }

    /** Never a raw database ID in the URL — the code itself is the only identifier a referral link ever needs to carry. */
    public function link(): JsonResponse
    {
        $customer = CustomerContext::customer();
        $url = 'https://'.$customer->shop->shopify_domain.'/?ref='.$customer->referral_code;

        return response()->json(['data' => ['referral_code' => $customer->referral_code, 'referral_url' => $url]]);
    }

    public function statistics(): JsonResponse
    {
        $customer = CustomerContext::customer();
        $referrals = $customer->referralsMade();

        return response()->json(['data' => [
            'total_referrals' => (clone $referrals)->count(),
            'successful_referrals' => $this->referrals->countCompletedForCustomer($customer),
            'pending_referrals' => (clone $referrals)->whereIn('status', ['clicked', 'registered', 'qualified'])->count(),
        ]]);
    }

    public function history(): JsonResponse
    {
        return PublicReferralResource::collection(
            $this->referrals->paginateForCustomer(CustomerContext::customer(), 20)
        )->response();
    }
}
