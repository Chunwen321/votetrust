<?php

namespace App\Services\CandidateRegistration;

use App\Contracts\RegistrationService;
use App\Models\Candidacy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * PIC: Wong Shun Bin
 * Proxy that validates and normalizes all input before database/file work begins.
 */
final class RegistrationProxy implements RegistrationService
{
    public function __construct(private readonly RegistrationService $realService) {}

    public function apply(
        int $userId,
        int $electionId,
        array $input,
        array $documents,
        UploadedFile $photo,
    ): Candidacy {
        if ($userId < 1 || $electionId < 1) {
            throw ValidationException::withMessages([
                'election_id' => 'The candidate or election is invalid.',
            ]);
        }

        $payload = [
            'position_sought' => trim((string) ($input['position_sought'] ?? '')),
            'manifesto' => trim((string) ($input['manifesto'] ?? '')),
            'supporting_details' => trim((string) ($input['supporting_details'] ?? '')) ?: null,
            'profile_photo' => $photo,
            'documents' => array_values($documents),
        ];

        /** @var array{position_sought: string, manifesto: string, supporting_details: string|null} $validated */
        $validated = Validator::make($payload, RegistrationRules::application())->validate();

        return $this->realService->apply(
            $userId,
            $electionId,
            [
                'position_sought' => $validated['position_sought'],
                'manifesto' => $validated['manifesto'],
                'supporting_details' => $validated['supporting_details'] ?? null,
            ],
            array_values($documents),
            $photo,
        );
    }
}
