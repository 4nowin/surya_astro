<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class OrderDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_type' => ['nullable', 'string', 'max:50'],
            'input_payload' => ['nullable', 'array'],
            'client_nonce' => ['nullable', 'string', 'max:191'],
        ];
    }
}
