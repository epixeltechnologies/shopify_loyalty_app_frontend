<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Structured request logging for the 'security' channel — separate from
 * the audit trail (audit_logs table), which records domain-meaningful
 * mutations. This logs every API call's shape (method, path, status,
 * duration, tenant) for operational monitoring and incident
 * investigation, and deliberately never logs request bodies (may contain
 * PII / access tokens).
 */
class LogApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        Log::channel('security')->info('api_request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'shop' => TenantContext::hasShop() ? TenantContext::shop()->shopify_domain : null,
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
