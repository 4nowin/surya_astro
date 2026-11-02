<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Horoscope\DailyHoroscopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyHoroscopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sign') && is_string($this->sign)) {
            $this->merge(['sign' => strtolower(trim($this->sign))]);
        }

        if ($this->has('lang') && is_string($this->lang)) {
            $this->merge(['lang' => strtolower(trim($this->lang))]);
        }
    }

    public function rules(): array
    {
        $validSigns = array_keys(DailyHoroscopeService::SIGNS);

        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sign' => ['nullable', 'string', Rule::in($validSigns)],
            'lang' => ['nullable', 'string', Rule::in(['en', 'hi'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'The date must match the format YYYY-MM-DD.',
            'sign.in' => 'The specified zodiac sign is invalid. Valid signs: ' . implode(', ', array_keys(DailyHoroscopeService::SIGNS)),
            'lang.in' => 'Language must be either "en" or "hi".',
        ];
    }
}
