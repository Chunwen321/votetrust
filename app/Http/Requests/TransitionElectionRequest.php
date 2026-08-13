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
                'submit_for_payment', 'submit_payment', 'approve', 'return_for_payment', 'reject',
                'start_voting', 'close_voting', 'cancel',
            ])],
            'current_password' => ['required', 'string'],
            'payment_reference' => ['nullable', 'required_if:action,submit_payment', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'reason' => ['nullable', 'required_if:action,reject,return_for_payment', 'string', 'max:250'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
