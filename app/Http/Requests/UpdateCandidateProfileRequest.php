<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PIC: Wong Shun Bin
 * Applies server-side validation to candidate profile changes.
 */
final class UpdateCandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:150',
                Rule::unique('user', 'email')->ignore($this->user()?->getKey()),
            ],
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\- ]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phoneNumber = trim((string) $this->input('phone_number', ''));

        $this->merge([
            'full_name' => trim((string) $this->input('full_name', '')),
            'email' => trim((string) $this->input('email', '')),
            'phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
        ]);
    }
}
