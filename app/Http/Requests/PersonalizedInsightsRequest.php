<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalizedInsightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user('sanctum') ?? $this->user()) !== null;
    }

    public function rules(): array
    {
        return [
            'profile_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'lang' => ['nullable', 'string', Rule::in(['en', 'hi'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'The date must match the format YYYY-MM-DD.',
            'profile_id.integer' => 'The profile ID must be an integer.',
            'lang.in' => 'Language must be either "en" or "hi".',
        ];
    }
}
