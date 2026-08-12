<?php

// PIC: Chew Zhexuan — User/Voter API contract for private election enrolments.

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PrivateVoterEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'electionId' => ['required', 'integer', 'min:1'],
            'organizerUserId' => ['required', 'integer', 'min:1'],
            'voters' => ['required', 'array', 'min:1', 'max:1000'],
            'voters.*.row' => ['required', 'integer', 'min:2', 'max:1000000'],
            'voters.*.fullName' => ['required', 'string', 'max:150'],
            'voters.*.identityNumber' => ['required', 'string', 'max:30'],
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'E',
            'message' => 'Invalid private voter enrolment request.',
            'requestID' => (string) $this->input('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
