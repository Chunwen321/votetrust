<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in([
                'submit_for_payment', 'verify_payment', 'approve', 'reject',
                'start_voting', 'close_voting', 'cancel',
            ])],
            'current_password' => ['required', 'string'],
            'payment_reference' => ['nullable', 'required_if:action,verify_payment', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'reason' => ['nullable', 'required_if:action,reject', 'string', 'max:250'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
