<?php

namespace App\Exceptions\Handlers;

use App\Exceptions\Billing\LimitReachedException;
use App\Exceptions\Billing\ShopInactiveException;
use App\Exceptions\Billing\SubscriptionRequiredException;
use App\Exceptions\Points\ManualAdjustmentsDisabledException;
use App\Exceptions\Points\NegativeBalanceNotAllowedException;
use App\Exceptions\Rewards\CustomerNotEligibleException;
use App\Exceptions\Rewards\InsufficientPointsException;
use App\Exceptions\Rewards\RewardNotRedeemableException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders every uncaught exception on an `api/*` request into the same
 * envelope as ApiResponse::error(), so API consumers (the frontend's
 * apiClient interceptor) never have to branch on which subsystem threw.
 * Registered from bootstrap/app.php's withExceptions() — this class is
 * intentionally NOT a Laravel ExceptionHandler subclass; Laravel 11+'s
 * functional exception configuration (`$exceptions->render(...)`) is
 * the supported extension point and keeps this logic testable in
 * isolation.
 *
 * Every branch also logs via App\Services\Audit — see logException() —
 * which is the "error tracking" hook: swap the log call for
 * app('sentry')->captureException($e) (or any APM SDK) in one place
 * once a tracking service is wired up, without touching every throw site.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $this->logException($e, $request);

        return match (true) {
            $e instanceof ManualAdjustmentsDisabledException => ApiResponse::error(
                message: $e->getMessage(),
                status: 403,
                errorCode: 'MANUAL_ADJUSTMENTS_DISABLED',
            ),
            $e instanceof InsufficientPointsException => ApiResponse::error(
                message: $e->getMessage(),
                status: 422,
                errorCode: 'INSUFFICIENT_POINTS',
                extra: ['current_balance' => $e->currentBalance, 'required' => $e->required],
            ),
            $e instanceof RewardNotRedeemableException => ApiResponse::error(
                message: $e->getMessage(),
                status: 422,
                errorCode: 'REWARD_NOT_REDEEMABLE',
                extra: ['reason' => $e->reasonCode],
            ),
            $e instanceof CustomerNotEligibleException => ApiResponse::error(
                message: $e->getMessage(),
                status: 403,
                errorCode: 'CUSTOMER_NOT_ELIGIBLE',
            ),
            $e instanceof NegativeBalanceNotAllowedException => ApiResponse::error(
                message: $e->getMessage(),
                status: 422,
                errorCode: 'NEGATIVE_BALANCE_NOT_ALLOWED',
                extra: ['current_balance' => $e->currentBalance, 'requested_change' => $e->requestedChange],
            ),
            $e instanceof ShopInactiveException => ApiResponse::error(
                message: 'This app is no longer installed on this store.',
                status: 410,
                errorCode: 'SHOP_UNINSTALLED',
            ),
            $e instanceof SubscriptionRequiredException => ApiResponse::error(
                message: 'An active subscription is required to use this app.',
                status: 402,
                errorCode: 'SUBSCRIPTION_REQUIRED',
            ),
            $e instanceof LimitReachedException => ApiResponse::error(
                message: $e->getMessage(),
                status: 403,
                errorCode: 'LIMIT_REACHED',
                extra: $e->toResponsePayload(),
            ),
            $e instanceof ValidationException => ApiResponse::error(
                message: 'The given data was invalid.',
                status: 422,
                errorCode: 'VALIDATION_FAILED',
                errors: $e->errors(),
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                message: 'Authentication required.',
                status: 401,
                errorCode: 'UNAUTHENTICATED',
            ),
            $e instanceof AuthorizationException => ApiResponse::error(
                message: $e->getMessage() ?: 'This action is unauthorized.',
                status: 403,
                errorCode: 'FORBIDDEN',
            ),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error(
                message: 'The requested resource could not be found.',
                status: 404,
                errorCode: 'NOT_FOUND',
            ),
            $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                message: 'Too many requests. Please slow down.',
                status: 429,
                errorCode: 'RATE_LIMITED',
                extra: array_filter(['retry_after' => $e->getHeaders()['Retry-After'] ?? null]),
            ),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                message: $e->getMessage() ?: 'Request failed.',
                status: $e->getStatusCode(),
                errorCode: $this->genericCodeFor($e->getStatusCode()),
            ),
            default => ApiResponse::error(
                message: App::isProduction() ? 'An unexpected error occurred.' : $e->getMessage(),
                status: 500,
                errorCode: 'INTERNAL_ERROR',
                extra: App::isProduction() ? [] : ['exception' => get_class($e), 'trace' => collect($e->getTrace())->take(5)->toArray()],
            ),
        };
    }

    private function genericCodeFor(int $status): string
    {
        return match (true) {
            $status === 402 => 'PAYMENT_REQUIRED',
            $status === 409 => 'CONFLICT',
            $status >= 500 => 'SERVER_ERROR',
            default => 'REQUEST_FAILED',
        };
    }

    /**
     * 4xx client errors are logged at `warning` (expected traffic —
     * bad input, missing auth) and never paged on; 5xx are logged at
     * `error` on the default channel, which is where an APM/error
     * tracker integration would tap in.
     */
    private function logException(Throwable $e, Request $request): void
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $level = $status >= 500 ? 'error' : 'warning';

        Log::log($level, $e->getMessage(), [
            'exception' => get_class($e),
            'status' => $status,
            'path' => $request->path(),
            'method' => $request->method(),
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }
}
