<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class EligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'voterId' => ['required', 'integer', 'min:1'],
            'electionId' => ['required', 'integer', 'min:1'],
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'E',
            'message' => 'Invalid eligibility request.',
            'requestID' => (string) $this->input('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
