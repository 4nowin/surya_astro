<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class DisputeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
