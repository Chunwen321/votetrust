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
            'election_id' => ['required', 'integer', 'exists:election,id'],
            ...RegistrationRules::application(),
            'candidate_introduction' => ['required', 'string', 'min:20', 'max:1000'],
            'reason_for_candidacy' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manifesto.min' => 'The manifesto must be at least 50 characters.',
            'profile_photo.mimetypes' => 'The candidate photo must be a genuine JPG or PNG image.',
            'documents.*.mimetypes' => 'Each supporting document must be a genuine PDF, JPG, or PNG file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $introduction = trim((string) $this->input('candidate_introduction', ''));
        $qualifications = trim((string) $this->input('qualifications', ''));
        $reason = trim((string) $this->input('reason_for_candidacy', ''));

        $this->merge([
            'manifesto' => trim((string) $this->input('manifesto', '')),
            'candidate_introduction' => $introduction,
            'qualifications' => $qualifications !== '' ? $qualifications : null,
            'reason_for_candidacy' => $reason,
        ]);
    }
}
