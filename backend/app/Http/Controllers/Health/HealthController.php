<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * OPS: health/readiness checks — see docs/MONITORING.md#health-checks.
 *
 * Deliberately unauthenticated (a load balancer / uptime monitor can't
 * carry App Bridge session tokens), which is exactly why every response
 * here is scrubbed to a bare minimum: a per-dependency boolean-ish
 * status string and, on failure, a generic reason — NEVER a connection
 * string, credential, hostname/port, Shopify access token, or exception
 * stack trace. A failed check's actual root cause belongs in the
 * `security`/application logs (accessible only to operators with server
 * access), not in the HTTP response body anyone can request.
 *
 * Two distinct endpoints, standard Kubernetes/load-balancer terminology:
 *   - `/health/live` (liveness): "is the PHP process able to handle a
 *     request at all" — no dependency checks, always fast, used to
 *     decide whether to KILL/restart a container.
 *   - `/health/ready` (readiness): "can this instance actually serve
 *     real traffic right now" — checks DB/Redis/queue/storage, used to
 *     decide whether to ROUTE traffic to this instance. Slower and
 *     more expensive than liveness; not meant to be polled every second
 *     by every client.
 *
 * Laravel's own built-in `/up` (bootstrap/app.php's `health: '/up'`)
 * remains registered and behaves like a basic liveness check already —
 * these are ADDITIONAL, more granular endpoints, not a replacement.
 */
class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $allHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $this->logFailure('database', $e);

            return ['status' => 'error', 'message' => 'Database connection failed.'];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $this->logFailure('redis', $e);

            return ['status' => 'error', 'message' => 'Redis connection failed.'];
        }
    }

    /** Confirms the queue connection itself is reachable — does NOT confirm workers are actively processing (see docs/MONITORING.md for how Horizon's own dashboard/metrics cover that separately). */
    private function checkQueue(): array
    {
        try {
            Queue::connection()->size();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $this->logFailure('queue', $e);

            return ['status' => 'error', 'message' => 'Queue connection failed.'];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default', 'local'));
            $probeFile = '.health-check-probe';
            $disk->put($probeFile, (string) now()->timestamp);
            $disk->delete($probeFile);

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $this->logFailure('storage', $e);

            return ['status' => 'error', 'message' => 'Storage is not writable.'];
        }
    }

    /** The real exception detail goes to logs (operator-only access), never the HTTP response. */
    private function logFailure(string $dependency, Throwable $e): void
    {
        Log::error("health_check_failed:{$dependency}", [
            'dependency' => $dependency,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }
}
