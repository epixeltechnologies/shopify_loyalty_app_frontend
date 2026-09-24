<?php

namespace App\Http\Requests\Customers;

use App\Http\Requests\BaseFormRequest;

class EnrollCustomerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'shopify_customer_id' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
