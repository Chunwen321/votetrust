<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Identity;
use App\Support\CountryList;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phoneNumber = trim((string) $this->input('phone_number'));

        $this->merge([
            'username' => trim((string) $this->input('username')),
            'full_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('full_name')) ?? ''),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'ic_number' => Identity::normaliseIcNumber((string) $this->input('ic_number')),
            'date_of_birth' => trim((string) $this->input('date_of_birth')),
            'nationality_country_code' => strtoupper(trim((string) $this->input('nationality_country_code'))),
            'phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:user,username'],
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:user,email'],
            'ic_number' => ['required', 'regex:/^\d{12}$/', 'unique:user,ic_number'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'nationality_country_code' => ['required', 'string', 'size:2', Rule::in(array_keys(CountryList::options()))],
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\- ]+$/'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateOfBirth = Identity::dateOfBirthFromMalaysianIc((string) $this->input('ic_number'));

            if ($dateOfBirth === null) {
                $validator->errors()->add('ic_number', 'The IC number must contain a valid Malaysian birth date.');

                return;
            }

            if ((string) $this->input('date_of_birth') !== $dateOfBirth->toDateString()) {
                $validator->errors()->add('date_of_birth', 'The date of birth must match the date encoded in the IC number.');
            }
        });
    }
}
