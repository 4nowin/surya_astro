<?php

namespace App\Http\Requests;

use App\Models\UserAstrologyProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAstrologyProfileRequest extends FormRequest
{
    protected function resolveProfile(): ?UserAstrologyProfile
    {
        $profile = $this->route('profile');
        if ($profile instanceof UserAstrologyProfile) {
            return $profile;
        }

        // Support 'primary' or 'default' keyword
        if ($profile === 'primary' || $profile === 'default') {
            return $this->user()?->astrologyProfiles()->where('is_default', true)->first();
        }

        if (is_numeric($profile) || is_string($profile)) {
            return UserAstrologyProfile::find($profile);
        }

        return null;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $routeParam = $this->route('profile');

        // Authenticated user is always authorized to manage/upsert their own primary profile
        if ($routeParam === 'primary' || $routeParam === 'default') {
            return true;
        }

        $profile = $this->resolveProfile();

        // If the profile does not exist at all, allow through so findOrFail throws 404 instead of 403
        if (!$profile) {
            return true;
        }

        // Strict ownership isolation: A user cannot modify another user's profile
        return (int) $profile->user_id === (int) $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $profile = $this->resolveProfile();
        $precision = $this->input('birth_time_precision', $profile?->birth_time_precision);

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'relationship' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'nullable', 'boolean'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'birth_time_precision' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    UserAstrologyProfile::PRECISION_VERIFIED,
                    UserAstrologyProfile::PRECISION_APPROXIMATE,
                    UserAstrologyProfile::PRECISION_UNKNOWN,
                ]),
            ],
            'birth_time' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s,H:i',
                Rule::requiredIf(fn () => $precision === UserAstrologyProfile::PRECISION_VERIFIED && empty($profile?->birth_time)),
                Rule::prohibitedIf(fn () => $precision === UserAstrologyProfile::PRECISION_UNKNOWN),
            ],
            'approximate_time_window' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(UserAstrologyProfile::VALID_TIME_WINDOWS),
                Rule::requiredIf(fn () => $precision === UserAstrologyProfile::PRECISION_APPROXIMATE && empty($profile?->approximate_time_window)),
                Rule::prohibitedIf(fn () => $precision !== UserAstrologyProfile::PRECISION_APPROXIMATE),
            ],
            'birth_city' => ['sometimes', 'required', 'string', 'max:100'],
            'birth_state' => ['nullable', 'string', 'max:100'],
            'birth_country' => ['sometimes', 'required', 'string', 'max:100'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('birth_time_precision')) {
            $precision = $this->input('birth_time_precision');
            if ($precision === UserAstrologyProfile::PRECISION_UNKNOWN) {
                $this->merge([
                    'birth_time' => null,
                    'approximate_time_window' => null,
                ]);
            } elseif ($precision === UserAstrologyProfile::PRECISION_VERIFIED) {
                $this->merge([
                    'approximate_time_window' => null,
                ]);
            }
        }
    }
}
