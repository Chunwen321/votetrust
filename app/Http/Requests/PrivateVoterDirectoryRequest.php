<?php

// PIC: Chew Zhexuan — User/Voter API contract for an organiser's private-voter directory.

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PrivateVoterDirectoryRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'perPage' => ['nullable', 'integer', 'min:10', 'max:100'],
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'E',
            'message' => 'Invalid private voter directory request.',
            'requestID' => (string) $this->input('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
