<?php

namespace App\Contracts;

use App\Models\Candidacy;
use Illuminate\Http\UploadedFile;

/**
 * PIC: Wong Shun Bin
 * Contract for the candidate-registration proxy and its real service.
 */
interface RegistrationService
{
    /**
     * @param  array{manifesto: string, candidate_introduction: string, qualifications: string|null, reason_for_candidacy: string}  $input
     * @param  list<UploadedFile>  $documents
     */
    public function apply(
        int $userId,
        int $electionId,
        array $input,
        array $documents,
        UploadedFile $photo,
    ): Candidacy;
}
