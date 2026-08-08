<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Security;

use App\Contracts\Clock;
use App\Services\Clock\SystemClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ApiRequestValidator
{
    private readonly Clock $clock;

    public function __construct(?Clock $clock = null)
    {
        $this->clock = $clock ?? new SystemClock;
    }

    /** @param array<string, mixed> $input
     * @return array{requestID:string,timestamp:string}
     *
     * @throws ValidationException
     */
    public function validateEnvelope(array $input): array
    {
        $validator = Validator::make($input, [
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'string'],
        ], [
            'requestID.regex' => 'requestID must contain 8-64 letters, numbers, underscores, or hyphens.',
        ]);

        $validator->after(function ($validator) use ($input): void {
            try {
                $requestedAt = CarbonImmutable::parse((string) ($input['timestamp'] ?? ''));
                if (abs($requestedAt->diffInSeconds($this->clock->now(), false))
                    > (int) config('election.api_clock_skew_seconds', 300)) {
                    $validator->errors()->add('timestamp', 'timestamp must be within five minutes of server time.');
                }
            } catch (Throwable) {
                $validator->errors()->add('timestamp', 'timestamp must be a valid ISO 8601 date-time.');
            }
        });

        $validated = $validator->validate();

        return [
            'requestID' => trim((string) $validated['requestID']),
            'timestamp' => trim((string) $validated['timestamp']),
        ];
    }
}
