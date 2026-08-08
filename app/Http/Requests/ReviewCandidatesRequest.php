<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array'],
            'decisions.*' => ['required', Rule::in(['Pending', 'Approved', 'Rejected'])],
            'current_password' => ['required', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
