<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignPrivateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:user,id'],
            'current_password' => ['required', 'string'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
