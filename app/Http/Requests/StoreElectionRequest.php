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

    protected function prepareForValidation(): void
    {
        $mode = strtoupper(trim((string) $this->input('eligibility_mode')));
        $this->merge([
            'eligibility_mode' => $mode,
            'eligibility_minimum_age' => $mode === EligibilityRules::PRIVATE
                ? null
                : $this->input('eligibility_minimum_age'),
            'eligibility_nationality' => $mode === EligibilityRules::PRIVATE
                ? 'ANY'
                : strtoupper(trim((string) $this->input('eligibility_nationality', 'ANY'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'position_sought' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\p{N} .,&()\/-]+$/u'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'eligibility_mode' => ['required', Rule::in([EligibilityRules::PUBLIC, EligibilityRules::PRIVATE])],
            'eligibility_minimum_age' => ['required_if:eligibility_mode,'.EligibilityRules::PUBLIC, 'nullable', 'integer', 'min:1', 'max:130'],
            'eligibility_nationality' => ['nullable', Rule::in(array_keys(EligibilityRules::nationalityOptions()))],
            'nomination_start' => ['required', 'date'],
            'nomination_end' => ['required', 'date', 'after:nomination_start'],
            'voting_start' => ['required', 'date', 'after_or_equal:nomination_end'],
            'voting_end' => ['required', 'date', 'after:voting_start'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
