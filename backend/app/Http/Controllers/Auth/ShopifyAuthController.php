<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Shopify\InvalidHmacException;
use App\Exceptions\Shopify\InvalidShopDomainException;
use App\Exceptions\Shopify\OAuthStateException;
use App\Exceptions\Shopify\TokenExchangeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InitiateInstallRequest;
use App\Http\Requests\Auth\OAuthCallbackRequest;
use App\Services\Shopify\ShopifyOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Classic OAuth install/callback flow, used only for the initial
 * install handshake (Shopify's recommended approach is otherwise to
 * rely on session tokens for all subsequent authenticated requests —
 * see docs/AUTHENTICATION.md). Deliberately thin: every real decision
 * (domain validation, HMAC, state, token exchange, shop provisioning)
 * lives in ShopifyOAuthService — this controller's only job is turning
 * validated input into a service call and a service result into an
 * HTTP response.
 */
class ShopifyAuthController extends Controller
{
    public function __construct(private readonly ShopifyOAuthService $oauth) {}

    public function redirect(InitiateInstallRequest $request): RedirectResponse|Response
    {
        try {
            return redirect($this->oauth->buildAuthorizeUrl($request->validated('shop')));
        } catch (InvalidShopDomainException $e) {
            return $this->errorResponse(400, $e->getMessage());
        }
    }

    public function callback(OAuthCallbackRequest $request): RedirectResponse|Response
    {
        try {
            $shop = $this->oauth->handleCallback(
                rawShopDomain: $request->validated('shop'),
                code: $request->validated('code'),
                hmac: $request->validated('hmac'),
                queryParams: $request->query(),
            );
        } catch (InvalidShopDomainException|InvalidHmacException|OAuthStateException $e) {
            // Client-error class of failures — malformed/forged/replayed
            // request. 401 rather than 400 for HMAC/state specifically,
            // since these represent an authentication failure, not just
            // bad input shape.
            return $this->errorResponse($e instanceof InvalidShopDomainException ? 400 : 401, $e->getMessage());
        } catch (TokenExchangeException $e) {
            // Shopify-side failure (their token endpoint rejected us or
            // errored) — not the merchant's fault, but not something we
            // can recover from inline either.
            return $this->errorResponse(502, $e->getMessage());
        }

        return redirect(config('app.frontend_url')."?shop={$shop->shopify_domain}");
    }

    /**
     * A plain text response is intentional here, not the JSON API error
     * envelope (App\Http\Responses\ApiResponse) — this route is hit
     * directly by a merchant's browser mid-redirect, not by the SPA's
     * apiClient, so a browser-renderable message is more useful than a
     * JSON blob.
     */
    private function errorResponse(int $status, string $message): Response
    {
        return response("Installation failed: {$message}", $status);
    }
}
