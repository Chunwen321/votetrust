<?php

// PIC: Low Wen Kai — organiser request for a live private-voter selection change.

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePrivateVoterSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'assigned' => ['required', 'boolean'],
        ];
    }
}
