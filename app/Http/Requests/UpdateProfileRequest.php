<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Identity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $attributes = [
            'full_name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('full_name')) ?? ''),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ];
        $user = $this->user();

        if ($user instanceof User && ! $user->isVerified() && ! $user->isAdmin()) {
            if (strtoupper((string) $user->nationality_country_code) === 'MY') {
                $attributes['ic_number'] = Identity::normaliseIcNumber((string) $this->input('ic_number'));
            } else {
                $attributes['passport_number'] = Identity::normalisePassportNumber((string) $this->input('passport_number'));
            }
        }

        $this->merge($attributes);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $user = $this->user();
        $rules = [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique('user', 'email')->ignore($this->user()?->getAuthIdentifier()),
            ],
        ];

        if (! $user instanceof User || $user->isVerified() || $user->isAdmin()) {
            return $rules + [
                'ic_number' => ['prohibited'],
                'passport_number' => ['prohibited'],
            ];
        }

        if (strtoupper((string) $user->nationality_country_code) === 'MY') {
            return $rules + [
                'ic_number' => ['required', 'string', 'regex:/^\d{12}$/', Rule::unique('user', 'ic_number')->ignore($user->getKey())],
                'passport_number' => ['prohibited'],
            ];
        }

        return $rules + [
            'ic_number' => ['prohibited'],
            'passport_number' => ['required', 'string', 'regex:/^[A-Z0-9]{6,20}$/', Rule::unique('user', 'passport_number')->ignore($user->getKey())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $user = $this->user();

        if (! $user instanceof User || $user->isVerified() || $user->isAdmin() || strtoupper((string) $user->nationality_country_code) !== 'MY') {
            return;
        }

        $validator->after(function (Validator $validator): void {
            if (Identity::dateOfBirthFromMalaysianIc((string) $this->input('ic_number')) === null) {
                $validator->errors()->add('ic_number', 'The IC number must contain a valid Malaysian birth date.');
            }
        });
    }
}
