<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

/**
 * First line of defense against a malformed/malicious `?shop=` value on
 * the install entry point — rejects anything that doesn't look like a
 * real Shopify domain via ordinary Laravel validation (a clean 422)
 * before the request ever reaches ShopifyOAuthService, which performs
 * the same check again (ShopDomainValidator) as the authoritative,
 * defense-in-depth layer. Two checks, one shared regex source of truth
 * — see ShopDomainValidator::PATTERN, mirrored here since a FormRequest
 * rule needs to be a string/closure, not a service call, to run before
 * the container has resolved anything for this request.
 */
class InitiateInstallRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'shop' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9\-]{0,59}\.myshopify\.com$/i'],
        ];
    }
}
