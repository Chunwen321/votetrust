<?php

namespace App\Services\CandidateRegistration;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;

/**
 * Keeps candidate-application entry rules consistent across election and candidate pages.
 */
final class CandidateApplicationAccess
{
    public function canApply(User $user, Election $election): bool
    {
        return $this->unavailableReason($user, $election) === null;
    }

    public function unavailableReason(User $user, Election $election): ?string
    {
        if ($user->isAdmin()) {
            return 'Administrator accounts cannot submit candidate applications.';
        }

        if (! $user->isVerified()) {
            return 'Complete eKYC profile verification before applying as a candidate.';
        }

        if ((int) $election->organizer_id === (int) $user->getKey()) {
            return 'You cannot apply as a candidate in an election that you organize.';
        }

        if (EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PUBLIC) {
            return 'This private election does not accept public candidate applications.';
        }

        if ($election->status !== Election::NOMINATION_OPEN) {
            return 'Candidate applications are not currently open for this election.';
        }

        $now = CarbonImmutable::now((string) config('app.timezone'));
        $startsAt = $election->nomination_start !== null
            ? CarbonImmutable::parse($election->nomination_start)
            : null;
        $endsAt = $election->nomination_end !== null
            ? CarbonImmutable::parse($election->nomination_end)
            : null;

        if ($startsAt !== null && $now->lessThan($startsAt)) {
            return 'Candidate applications have not opened yet.';
        }

        if ($endsAt !== null && $now->greaterThan($endsAt)) {
            return 'Candidate applications for this election have closed.';
        }

        if (trim((string) $election->position_sought) === '') {
            return 'This election does not have a candidate position configured.';
        }

        if ($election->organizer_id === null) {
            return 'This election does not have an organizer who can receive the candidate deposit.';
        }

        $existingApplication = Candidacy::query()
            ->where('user_id', $user->getKey())
            ->where('election_id', $election->getKey())
            ->first();

        if (
            $existingApplication instanceof Candidacy
            && ! in_array($existingApplication->status, [Candidacy::REJECTED, Candidacy::CANCELLED], true)
        ) {
            return 'You already have a candidate application for this election.';
        }

        return null;
    }
}
