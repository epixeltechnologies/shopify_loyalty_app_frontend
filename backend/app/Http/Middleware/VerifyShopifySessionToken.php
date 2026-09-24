<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the App Bridge session token JWT sent as a Bearer token on
 * every embedded-app API request, per Shopify's session token auth spec.
 * On success, stores the shop domain (the JWT `dest` claim) on the
 * request for ResolveShopFromSession to pick up.
 */
class VerifyShopifySessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing session token.'], 401);
        }

        try {
            $decoded = JWT::decode($token, new Key(config('shopify.api_secret'), 'HS256'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid or expired session token.'], 401);
        }

        if (($decoded->aud ?? null) !== config('shopify.api_key')) {
            return response()->json(['message' => 'Session token audience mismatch.'], 401);
        }

        $domain = parse_url($decoded->dest ?? '', PHP_URL_HOST);
        $request->attributes->set('shopify_domain', $domain);

        return $next($request);
    }
}
