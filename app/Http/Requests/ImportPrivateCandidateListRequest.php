<?php

// PIC: Low Wen Kai — organiser upload request for a private-election candidate spreadsheet.

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ImportPrivateCandidateListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'candidate_list' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:2048'],
            'current_password' => ['required', 'string'],
        ];
    }
}
