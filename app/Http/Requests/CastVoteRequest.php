<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

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
            'candidate_id' => ['bail', 'required', 'integer', 'min:1'],
            'request_id' => ['bail', 'required', 'string', 'regex:/\A[A-Za-z0-9_-]{8,64}\z/'],
            'timestamp' => ['bail', 'required', 'string', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('timestamp')) {
                    return;
                }

                try {
                    $timestamp = CarbonImmutable::parse((string) $this->input('timestamp'));
                } catch (Throwable) {
                    $validator->errors()->add('timestamp', 'The ballot timestamp is invalid. Reload it before voting.');

                    return;
                }

                if (abs(now()->diffInSeconds($timestamp, false))
                    > max(1, (int) config('voting.request_max_age_seconds', 300))) {
                    $validator->errors()->add('timestamp', 'The ballot has expired. Reload it before voting.');
                }
            },
        ];
    }
}
