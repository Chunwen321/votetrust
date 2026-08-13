<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CountryList;
use App\Support\Identity;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phoneNumber = trim((string) $this->input('phone_number'));
        $icNumber = Identity::normaliseIcNumber((string) $this->input('ic_number'));
        $passportNumber = Identity::normalisePassportNumber((string) $this->input('passport_number'));

        $this->merge([
            'full_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('full_name')) ?? ''),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            // Disabled inputs are not submitted by the browser. Store missing identity fields as null so that
            // Laravel's nullable validation rule can correctly skip the unused document type.
            'ic_number' => $icNumber !== '' ? $icNumber : null,
            'passport_number' => $passportNumber !== '' ? $passportNumber : null,
            'nationality_country_code' => strtoupper(trim((string) $this->input('nationality_country_code'))),
            'date_of_birth' => trim((string) $this->input('date_of_birth')) ?: null,
            'phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:user,email'],
            'nationality_country_code' => ['required', 'string', 'size:2', Rule::in(array_keys(CountryList::options()))],
            'ic_number' => [
                Rule::requiredIf(fn (): bool => $this->input('nationality_country_code') === 'MY'),
                'nullable',
                'regex:/^\d{12}$/',
                'unique:user,ic_number',
            ],
            'passport_number' => [
                Rule::requiredIf(fn (): bool => $this->input('nationality_country_code') !== 'MY'),
                'nullable',
                'regex:/^[A-Z0-9]{6,20}$/',
                'unique:user,passport_number',
            ],
            'date_of_birth' => [
                Rule::requiredIf(fn (): bool => $this->input('nationality_country_code') !== 'MY'),
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'phone_number' => [
                'required',
                'string',
                'max:30',
                'regex:/^(?:\+|00)?[0-9().\- ]+$/',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    $digits = preg_replace('/\D/', '', (string) $value) ?? '';

                    if (str_starts_with(trim((string) $value), '00')) {
                        $digits = substr($digits, 2);
                    }

                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('The phone number must contain between 7 and 15 digits.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('nationality_country_code') !== 'MY') {
                return;
            }

            $dateOfBirth = Identity::dateOfBirthFromMalaysianIc((string) $this->input('ic_number'));

            if ($dateOfBirth === null) {
                $validator->errors()->add('ic_number', 'The IC number must contain a valid Malaysian birth date.');
            }
        });
    }
}
