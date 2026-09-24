<?php

namespace App\Http\Controllers\Api\V1\Referrals;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicReferralResource;
use App\Models\Customer;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use Illuminate\Http\JsonResponse;

/**
 * The "Customer API" section — prepared for a future storefront widget
 * (this app has no customer-facing auth layer yet, same scope note as
 * the rewards engine's CustomerRewardController). Every response
 * excludes fraud information per the task's explicit requirement — see
 * PublicReferralResource.
 */
class CustomerReferralController extends Controller
{
    public function __construct(private readonly ReferralRepositoryInterface $referrals) {}

    /** Get referral code. */
    public function code(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(['data' => ['referral_code' => $customer->referral_code]]);
    }

    /** Get referral link — built from the code, never a raw database ID. */
    public function link(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $url = 'https://'.$customer->shop->shopify_domain.'/?ref='.$customer->referral_code;

        return response()->json(['data' => ['referral_code' => $customer->referral_code, 'referral_url' => $url]]);
    }

    /** Get referral statistics — this customer's own referral performance, not the shop-wide admin view. */
    public function statistics(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $referrals = $customer->referralsMade();

        return response()->json(['data' => [
            'total_referrals' => (clone $referrals)->count(),
            'successful_referrals' => $this->referrals->countCompletedForCustomer($customer),
            'pending_referrals' => (clone $referrals)->whereIn('status', ['clicked', 'registered', 'qualified'])->count(),
        ]]);
    }

    /** Get referral history. */
    public function history(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return PublicReferralResource::collection(
            $this->referrals->paginateForCustomer($customer)
        )->response();
    }
}
