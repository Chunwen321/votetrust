<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// PIC: Goh Chun Wen — Voting Process module.
class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'min:1'],
            'request_id' => ['required', 'regex:/\A[A-Za-z0-9_-]{8,64}\z/'],
            'timestamp' => ['required', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $timestamp = $this->date('timestamp');
                if ($timestamp === null || abs(now()->diffInSeconds($timestamp, false)) > 300) {
                    $validator->errors()->add('timestamp', 'The ballot has expired. Reload it before voting.');
                }
            },
        ];
    }
}
