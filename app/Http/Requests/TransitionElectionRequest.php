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
        $action = (string) $this->input('action');
        $requiresCurrentPassword = $this->user()?->isAdmin() === true
            || in_array($action, ['start_nomination', 'cancel'], true);

        return [
            'action' => ['required', 'string', Rule::in([
                'submit_for_payment', 'submit_payment', 'verify_payment', 'approve', 'return_for_payment', 'reject',
                'start_nomination', 'cancel',
            ])],
            'current_password' => [$requiresCurrentPassword ? 'required' : 'nullable', 'string'],
            'reason' => ['nullable', 'required_if:action,cancel,reject,return_for_payment', 'string', 'min:5', 'max:250'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
