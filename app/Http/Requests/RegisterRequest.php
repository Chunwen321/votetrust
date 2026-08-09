<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Identity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'full_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('full_name')) ?? ''),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'ic_number' => Identity::normaliseIcNumber((string) $this->input('ic_number')),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:users,username'],
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'ic_number' => ['required', 'regex:/^\d{12}$/', 'unique:users,ic_number'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (Identity::dateOfBirthFromMalaysianIc((string) $this->input('ic_number')) === null) {
                $validator->errors()->add('ic_number', 'The IC number must contain a valid Malaysian birth date.');
            }
        });
    }
}
