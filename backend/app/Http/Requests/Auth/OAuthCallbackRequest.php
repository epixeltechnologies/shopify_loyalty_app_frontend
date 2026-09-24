<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

/**
 * Confirms the callback carries every parameter the flow needs before
 * ShopifyOAuthService does any real work. This checks *presence/shape*
 * only — the HMAC and state values themselves are cryptographically
 * validated by the service, not here (a FormRequest rule can't safely
 * hold the HMAC secret comparison logic and shouldn't try to).
 */
class OAuthCallbackRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'shop' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9\-]{0,59}\.myshopify\.com$/i'],
            'code' => ['required', 'string'],
            'hmac' => ['required', 'string'],
            'state' => ['required', 'string'],
            'timestamp' => ['nullable', 'string'],
        ];
    }
}
