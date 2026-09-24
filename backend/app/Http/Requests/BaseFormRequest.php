<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every FormRequest in the app extends this rather than Laravel's
 * FormRequest directly, so validation behaves identically everywhere
 * without each request class re-deciding it:
 *
 *   - `authorize()` defaults to `true`. Tenant scoping (TenantScope) and
 *     the `tenant.resolve`/`subscription.active`/`feature:{key}`
 *     middleware already run before a FormRequest is ever constructed,
 *     and instance-level authorization is done explicitly via policies
 *     in the controller (`$this->authorize(...)`) — see
 *     docs/API_ARCHITECTURE.md#controller-conventions — not by
 *     overriding `authorize()` per-request. A request class only
 *     overrides this when it has a genuinely request-specific rule
 *     beyond "the middleware already checked."
 *   - Every failing field is reported in one response (Laravel's
 *     default — deliberately NOT `stopOnFirstFailure`), since an API
 *     client wants to fix every invalid field in one round trip rather
 *     than discovering them one at a time.
 *   - `failedValidation()` still throws the framework's normal
 *     ValidationException — App\Exceptions\Handlers\ApiExceptionRenderer
 *     is what shapes it into the standard envelope
 *     (`error_code: VALIDATION_FAILED`), so this override exists only
 *     to guarantee the exception is always JSON, never a redirect, even
 *     if a future request is hit outside the `api/*` path matcher.
 */
abstract class BaseFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Guarantees a ValidationException is thrown regardless of the
     * request's Accept header — API clients that forget
     * `Accept: application/json` still get JSON, never a redirect to a
     * login page that doesn't exist for this API. The exception itself
     * is shaped into the standard error envelope by
     * App\Exceptions\Handlers\ApiExceptionRenderer, not here.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw ValidationException::withMessages($validator->errors()->toArray());
    }

    /**
     * Turns `shopify_customer_id` into "Shopify customer ID" instead of
     * the default "shopify customer id" in generated messages — cheap
     * to get for free on every request rather than hand-writing
     * `attributes()` per class.
     */
    public function attributes(): array
    {
        return collect($this->rules())
            ->keys()
            ->mapWithKeys(fn (string $field) => [$field => (string) Str::of($field)->replace('_', ' ')->replace('.', ' ')])
            ->all();
    }
}
