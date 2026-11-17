<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deliverable_text' => ['required', 'string'],
            'deliverable_attachments' => ['nullable', 'array'],
            'deliverable_attachments.*' => ['string'],
        ];
    }
}
