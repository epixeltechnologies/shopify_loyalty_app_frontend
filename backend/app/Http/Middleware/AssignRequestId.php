<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every request with a correlation ID — reused from the
 * `X-Request-Id` header if the client (or an upstream load balancer)
 * already set one, otherwise generated fresh. Attached to the request
 * (for other middleware/exception handling to read), echoed back on the
 * response, and pushed onto every subsequent Log call via
 * Log::withContext() so a single request's log lines — across
 * LogApiRequests, the audit channel, and any exception — can be
 * grep'd/joined by one ID during incident investigation.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        logger()->withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
