<?php

// PIC: Chew Zhexuan — consent required before sending identity details to eKYC.

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        if ($this->user() instanceof User && $this->user()->isAdmin()) {
            return [];
        }

        return [
            'ekyc_consent' => ['accepted'],
        ];
    }
}
