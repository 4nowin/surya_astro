<?php

namespace App\Http\Requests;

use App\Models\UserAstrologyProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAstrologyProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'birth_time_precision' => [
                'required',
                'string',
                Rule::in([
                    UserAstrologyProfile::PRECISION_VERIFIED,
                    UserAstrologyProfile::PRECISION_APPROXIMATE,
                    UserAstrologyProfile::PRECISION_UNKNOWN,
                ]),
            ],
            'birth_time' => [
                'nullable',
                'date_format:H:i:s,H:i',
                Rule::requiredIf(fn () => $this->input('birth_time_precision') === UserAstrologyProfile::PRECISION_VERIFIED),
                Rule::prohibitedIf(fn () => $this->input('birth_time_precision') === UserAstrologyProfile::PRECISION_UNKNOWN),
            ],
            'approximate_time_window' => [
                'nullable',
                'string',
                Rule::in(UserAstrologyProfile::VALID_TIME_WINDOWS),
                Rule::requiredIf(fn () => $this->input('birth_time_precision') === UserAstrologyProfile::PRECISION_APPROXIMATE),
                Rule::prohibitedIf(fn () => $this->input('birth_time_precision') !== UserAstrologyProfile::PRECISION_APPROXIMATE),
            ],
            'birth_city' => ['required', 'string', 'max:100'],
            'birth_state' => ['nullable', 'string', 'max:100'],
            'birth_country' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Enforce safeguards: strip invalid fields for precision types
        if ($this->input('birth_time_precision') === UserAstrologyProfile::PRECISION_UNKNOWN) {
            $this->merge([
                'birth_time' => null,
                'approximate_time_window' => null,
            ]);
        } elseif ($this->input('birth_time_precision') === UserAstrologyProfile::PRECISION_VERIFIED) {
            $this->merge([
                'approximate_time_window' => null,
            ]);
        }
    }
}
