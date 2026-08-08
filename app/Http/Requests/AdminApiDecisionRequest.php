<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminApiDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'string'],
            'decision' => ['required', 'string', Rule::in(['APPROVE', 'REJECT', 'approve', 'reject'])],
            'adminUserID' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'required_if:decision,REJECT,reject', 'string', 'min:5', 'max:250'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
