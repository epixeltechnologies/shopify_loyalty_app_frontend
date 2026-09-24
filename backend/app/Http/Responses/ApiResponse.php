<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Single source of truth for the API's JSON envelope shape, so every
 * controller and every exception-triggered error response looks the
 * same regardless of which code path produced it. Controllers should
 * prefer returning a Resource directly (Laravel wraps it in {"data": ...}
 * already, matching this envelope) — this class exists for the cases a
 * Resource doesn't fit: bare success acknowledgements, and the error
 * shape used by App\Exceptions\Handlers\ApiExceptionRenderer.
 *
 * Success shape:  {"data": ..., "meta"?: {...}}
 * Error shape:    {"message": "...", "error_code": "...", "errors"?: {...}}
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $payload = $data instanceof JsonResource || $data instanceof ResourceCollection
            ? $data->response()->getData(true)
            : ['data' => $data];

        if ($meta) {
            $payload['meta'] = [...($payload['meta'] ?? []), ...$meta];
        }

        return response()->json($payload, $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?string $errorCode = null,
        ?array $errors = null,
        array $extra = [],
    ): JsonResponse {
        return response()->json(array_filter([
            'message' => $message,
            'error_code' => $errorCode,
            'errors' => $errors,
            ...$extra,
        ], fn ($v) => $v !== null), $status);
    }
}
