<?php

// PIC: Low Wen Kai — organiser upload request for a private-election voter spreadsheet.

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ImportPrivateVoterListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'voter_list' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:2048'],
        ];
    }
}
