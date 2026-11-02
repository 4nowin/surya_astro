<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DailyPanchangRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'tz' => ['required', 'string', 'timezone:all'],
            'elevation' => ['sometimes', 'nullable', 'numeric', 'between:-500,9000'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'The date field is required.',
            'date.date_format' => 'The date must match the format YYYY-MM-DD.',
            'lat.required' => 'The latitude coordinate is required.',
            'lat.between' => 'Latitude must be between -90 and 90 degrees.',
            'lon.required' => 'The longitude coordinate is required.',
            'lon.between' => 'Longitude must be between -180 and 180 degrees.',
            'tz.required' => 'The canonical IANA timezone is required.',
            'tz.timezone' => 'The timezone must be a valid IANA timezone identifier.',
        ];
    }
}
