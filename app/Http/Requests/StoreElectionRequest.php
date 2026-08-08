<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Requests;

use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'eligibility_mode' => ['required', Rule::in([EligibilityRules::PUBLIC, EligibilityRules::RESTRICTED])],
            'eligibility_minimum_age' => ['nullable', 'integer', Rule::in(EligibilityRules::minimumAgeOptions())],
            'eligibility_occupation' => ['nullable', Rule::in(array_keys(EligibilityRules::occupationOptions()))],
            'eligibility_nationality' => ['nullable', Rule::in(array_keys(EligibilityRules::nationalityOptions()))],
            'nomination_start' => ['required', 'date'],
            'nomination_end' => ['required', 'date', 'after:nomination_start'],
            'voting_start' => ['required', 'date', 'after_or_equal:nomination_end'],
            'voting_end' => ['required', 'date', 'after:voting_start'],
            'hosting_fee' => ['required', 'numeric', 'min:1', 'max:10000'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (strtoupper((string) $this->input('eligibility_mode')) !== EligibilityRules::RESTRICTED) {
                return;
            }

            $age = $this->input('eligibility_minimum_age');
            $occupation = strtoupper((string) $this->input('eligibility_occupation', 'ANY'));
            $nationality = strtoupper((string) $this->input('eligibility_nationality', 'ANY'));
            if (($age === null || $age === '') && $occupation === 'ANY' && $nationality === 'ANY') {
                $validator->errors()->add('eligibility_criteria', 'Select at least one eligibility requirement.');
            }
        });
    }
}
