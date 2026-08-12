<?php

namespace App\Services\CandidateRegistration;

use App\Contracts\RegistrationService;
use App\Models\Candidacy;
use App\Models\CandidateDocument;
use App\Models\Election;
use App\Models\Payment;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * PIC: Wong Shun Bin
 * Performs transaction-safe candidate registration after the proxy validates input.
 */
final class RealRegistrationService implements RegistrationService
{
    public function __construct(private readonly CandidateFileStorage $fileStorage) {}

    public function apply(
        int $userId,
        int $electionId,
        array $input,
        array $documents,
        UploadedFile $photo,
    ): Candidacy {
        $newPaths = [];
        $obsoletePaths = [];

        try {
            $candidacy = DB::transaction(function () use (
                $userId,
                $electionId,
                $input,
                $documents,
                $photo,
                &$newPaths,
                &$obsoletePaths,
            ): Candidacy {
                $election = Election::query()->lockForUpdate()->find($electionId);

                if (! $election instanceof Election || ! $this->acceptsNominations($election)) {
                    throw ValidationException::withMessages([
                        'election_id' => 'This election is not accepting nominations.',
                    ]);
                }

                if (EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PUBLIC) {
                    throw ValidationException::withMessages([
                        'election_id' => 'This private election does not accept public candidate applications.',
                    ]);
                }

                if ($election->organizer_id === null) {
                    throw ValidationException::withMessages([
                        'election_id' => 'This election does not have an organizer who can receive the deposit.',
                    ]);
                }

                $candidacy = Candidacy::query()
                    ->where('user_id', $userId)
                    ->where('election_id', $electionId)
                    ->lockForUpdate()
                    ->first();

                if ($candidacy instanceof Candidacy && $candidacy->status !== Candidacy::REJECTED) {
                    throw ValidationException::withMessages([
                        'election_id' => 'You can only reapply for this election after your previous application is rejected.',
                    ]);
                }

                if (! $candidacy instanceof Candidacy) {
                    $candidacy = new Candidacy;
                    $candidacy->user_id = $userId;
                    $candidacy->election_id = $electionId;
                } else {
                    if (is_string($candidacy->profile_photo) && $candidacy->profile_photo !== '') {
                        $obsoletePaths[] = $candidacy->profile_photo;
                    }

                    $previousDocuments = CandidateDocument::query()
                        ->where('candidacy_id', $candidacy->getKey())
                        ->lockForUpdate()
                        ->get();

                    foreach ($previousDocuments as $previousDocument) {
                        if (is_string($previousDocument->stored_name)) {
                            $obsoletePaths[] = $previousDocument->stored_name;
                        }
                    }

                    CandidateDocument::query()
                        ->where('candidacy_id', $candidacy->getKey())
                        ->delete();
                }

                $photoData = $this->fileStorage->storePhoto($photo);
                $newPaths[] = $photoData['path'];

                $payment = new Payment;
                $payment->amount = (string) config('candidate.deposit_amount', '10.00');
                $payment->status = 'Pending';
                $payment->organizer_id = $election->organizer_id;
                $payment->candidate_id = $userId;
                $payment->save();

                $candidacy->payment_id = $payment->getKey();
                $candidacy->status = 'Pending';
                $candidacy->registered_at = now();
                $candidacy->position_sought = $input['position_sought'];
                $candidacy->manifesto = $input['manifesto'];
                $candidacy->candidate_introduction = $input['candidate_introduction'];
                $candidacy->qualifications = $input['qualifications'];
                $candidacy->reason_for_candidacy = $input['reason_for_candidacy'];
                $candidacy->profile_photo = $photoData['path'];
                $candidacy->profile_photo_mime = $photoData['mime'];
                $candidacy->save();

                foreach ($documents as $document) {
                    $documentData = $this->fileStorage->storeDocument($document);
                    $newPaths[] = $documentData['path'];

                    $candidateDocument = new CandidateDocument;
                    $candidateDocument->candidacy_id = $candidacy->getKey();
                    $candidateDocument->original_name = $documentData['original_name'];
                    $candidateDocument->stored_name = $documentData['path'];
                    $candidateDocument->mime_type = $documentData['mime'];
                    $candidateDocument->uploaded_at = now();
                    $candidateDocument->save();
                }

                return $candidacy;
            });
        } catch (Throwable $exception) {
            $this->fileStorage->deleteMany($newPaths);

            throw $exception;
        }

        $this->fileStorage->deleteMany($obsoletePaths);

        return $candidacy->refresh();
    }

    private function acceptsNominations(Election $election): bool
    {
        if ($election->status !== 'NOMINATION_OPEN') {
            return false;
        }

        $now = CarbonImmutable::now();
        $startsAt = $election->nomination_start !== null
            ? CarbonImmutable::parse($election->nomination_start)
            : null;
        $endsAt = $election->nomination_end !== null
            ? CarbonImmutable::parse($election->nomination_end)
            : null;

        return ($startsAt === null || $now->greaterThanOrEqualTo($startsAt))
            && ($endsAt === null || $now->lessThanOrEqualTo($endsAt));
    }
}
