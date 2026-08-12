<?php

// PIC: Chew Zhexuan — User/Voter API contract for live private-voter selection.

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PrivateVoterSelectionRequest extends FormRequest
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
            'userId' => ['required', 'integer', 'min:1'],
            'assigned' => ['required', 'boolean'],
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'E',
            'message' => 'Invalid private voter selection request.',
            'requestID' => (string) $this->input('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
