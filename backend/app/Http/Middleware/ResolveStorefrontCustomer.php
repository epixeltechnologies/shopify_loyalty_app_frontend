<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Customers\CustomerService;
use App\Support\Tenancy\CustomerContext;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STOREFRONT AUTH: resolves WHICH customer this verified request is
 * for. Requires `logged_in_customer_id` — Shopify only includes this
 * in an app-proxy request when a real customer is genuinely logged
 * into the storefront (and it's covered by the same signature
 * VerifyShopifyAppProxyRequest already checked, so it can't be forged
 * independently of the rest of the request). A storefront visitor who
 * isn't logged in gets a clean 401 here, not a confusing downstream
 * error from a controller assuming a customer exists.
 *
 * Auto-enrolls a Shopify customer who is logged in but has never
 * interacted with the loyalty program before — reuses
 * CustomerService::enroll() (the SAME enrollment path
 * HandleCustomerCreatedJob uses), never a second, parallel
 * "create a loyalty customer" implementation. This is what makes
 * "the customer belongs to the correct tenant" trivially true: a
 * customer is only ever created scoped to the shop TenantContext
 * already resolved, and only ever looked up within that same scope.
 */
class ResolveStorefrontCustomer
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerService $enrollment,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shopifyCustomerId = $request->query('logged_in_customer_id');

        if (! $shopifyCustomerId || ! ctype_digit((string) $shopifyCustomerId)) {
            return response()->json(['message' => 'You must be logged in to view your loyalty account.'], 401);
        }

        $shop = TenantContext::shop();
        $customer = $this->customers->findByShopifyCustomerId($shop, (string) $shopifyCustomerId);

        if (! $customer) {
            $customer = $this->enrollment->enroll($shop, ['shopify_customer_id' => (string) $shopifyCustomerId]);
        }

        if ($customer->status !== 'active') {
            return response()->json(['message' => 'This account is not available.'], 403);
        }

        CustomerContext::set($customer);
        $request->attributes->set('storefront_customer', $customer);

        return $next($request);
    }
}
