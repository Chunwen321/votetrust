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
            'manifesto' => trim((string) ($input['manifesto'] ?? '')),
            'candidate_introduction' => trim((string) ($input['candidate_introduction'] ?? '')),
            'qualifications' => trim((string) ($input['qualifications'] ?? '')) ?: null,
            'reason_for_candidacy' => trim((string) ($input['reason_for_candidacy'] ?? '')),
            'profile_photo' => $photo,
            'documents' => array_values($documents),
        ];

        /** @var array{manifesto: string, candidate_introduction: string, qualifications: string|null, reason_for_candidacy: string} $validated */
        $validated = Validator::make($payload, RegistrationRules::application())->validate();

        return $this->realService->apply(
            $userId,
            $electionId,
            [
                'manifesto' => $validated['manifesto'],
                'candidate_introduction' => $validated['candidate_introduction'],
                'qualifications' => $validated['qualifications'] ?? null,
                'reason_for_candidacy' => $validated['reason_for_candidacy'],
            ],
            array_values($documents),
            $photo,
        );
    }
}
