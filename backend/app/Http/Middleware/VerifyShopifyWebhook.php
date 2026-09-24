<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Shopify-Hmac-Sha256 header against the raw request body
 * using a constant-time comparison, per Shopify's webhook verification
 * requirements. Must run before any body parsing/mutation.
 *
 * OPS HARDENING: failures are logged to the dedicated `security` channel
 * (90-day retention, see config/logging.php and docs/MONITORING.md) —
 * this was previously unlogged entirely. A signature failure is either a
 * misconfigured `SHOPIFY_API_SECRET` (an operational problem worth
 * noticing quickly) or a genuine forged-webhook attempt (a security
 * event worth an audit trail) — either way, silently returning 401 with
 * no record made it impossible to investigate after the fact. Never
 * logs the request body itself (could contain customer data) or the
 * computed/expected signature values (logging either half of an HMAC
 * comparison is unnecessary and mildly sensitive) — only the shop
 * domain header (if present) and the failure reason.
 */
class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $hmacHeader = $request->header(config('shopify.webhook_verification.header'));
        $body = $request->getContent();

        if (! $hmacHeader || ! $body) {
            Log::channel('security')->warning('webhook_signature_missing', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Missing webhook signature.'], 401);
        }

        $computed = base64_encode(hash_hmac('sha256', $body, config('shopify.api_secret'), true));

        if (! hash_equals($computed, $hmacHeader)) {
            Log::channel('security')->warning('webhook_signature_invalid', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
