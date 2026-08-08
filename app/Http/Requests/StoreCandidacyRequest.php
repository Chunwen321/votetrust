<?php

namespace App\Http\Requests;

use App\Services\CandidateRegistration\RegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PIC: Wong Shun Bin
 * Validates a candidate application before it reaches the registration proxy.
 */
final class StoreCandidacyRequest extends FormRequest
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
            'election_id' => ['required', 'integer', 'exists:elections,id'],
            ...RegistrationRules::application(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position_sought.regex' => 'The position may contain letters, numbers, spaces, and common punctuation only.',
            'manifesto.min' => 'The manifesto must be at least 50 characters.',
            'profile_photo.mimetypes' => 'The candidate photo must be a genuine JPG or PNG image.',
            'documents.*.mimetypes' => 'Each supporting document must be a genuine PDF, JPG, or PNG file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $supportingDetails = trim((string) $this->input('supporting_details', ''));

        $this->merge([
            'position_sought' => trim((string) $this->input('position_sought', '')),
            'manifesto' => trim((string) $this->input('manifesto', '')),
            'supporting_details' => $supportingDetails !== '' ? $supportingDetails : null,
        ]);
    }
}
