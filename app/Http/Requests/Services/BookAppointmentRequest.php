<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'appointment_type' => ['nullable', 'string', 'in:online,in_person,phone_call,puja,vastu_visit,other'],
            'delivery_method' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_nonce' => ['nullable', 'string', 'max:191'],
        ];
    }
}
