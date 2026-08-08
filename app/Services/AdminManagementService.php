<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Models\Candidacy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\User;
use App\Models\VoterEnrollment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminManagementService
{
    /** @var list<string> */
    public const VOTER_STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'];

    /** @var list<string> */
    public const CANDIDATE_STATUSES = ['Pending', 'Approved', 'Rejected'];

    /** @return array{elections:int,active:int,voters:int,pending_candidates:int} */
    public function dashboardCounts(): array
    {
        return [
            'elections' => Election::query()->count(),
            'active' => Election::query()
                ->whereIn('status', ['APPROVED', 'NOMINATION_OPEN', 'VOTING_OPEN'])
                ->count(),
            'voters' => VoterEnrollment::query()->where('status', 'APPROVED')->count(),
            'pending_candidates' => Candidacy::query()
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->count(),
        ];
    }

    /** @return EloquentCollection<int, Election> */
    public function recentElections(int $limit = 5): EloquentCollection
    {
        return Election::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /** @return EloquentCollection<int, VoterEnrollment> */
    public function voters(Election $election): EloquentCollection
    {
        $voterTable = (new VoterEnrollment)->getTable();
        $userTable = (new User)->getTable();

        return VoterEnrollment::query()
            ->select([
                "{$voterTable}.*",
                "{$userTable}.username",
                "{$userTable}.full_name",
                "{$userTable}.email",
                "{$userTable}.account_status",
            ])
            ->join($userTable, "{$userTable}.id", '=', "{$voterTable}.user_id")
            ->where("{$voterTable}.election_id", $election->getKey())
            ->orderByRaw("CASE {$voterTable}.status WHEN 'PENDING' THEN 1 WHEN 'REJECTED' THEN 2 WHEN 'SUSPENDED' THEN 3 ELSE 4 END")
            ->orderBy("{$userTable}.username")
            ->get();
    }

    /** @return EloquentCollection<int, Candidacy> */
    public function candidates(Election $election): EloquentCollection
    {
        $candidacyTable = (new Candidacy)->getTable();
        $userTable = (new User)->getTable();

        return Candidacy::query()
            ->select([
                "{$candidacyTable}.*",
                "{$userTable}.username",
                "{$userTable}.full_name",
                "{$userTable}.email",
                "{$userTable}.account_status",
            ])
            ->join($userTable, "{$userTable}.id", '=', "{$candidacyTable}.candidate_id")
            ->where("{$candidacyTable}.election_id", $election->getKey())
            ->orderByRaw("CASE LOWER({$candidacyTable}.status) WHEN 'pending' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->orderBy("{$userTable}.username")
            ->get();
    }

    /** @return EloquentCollection<int, User> */
    public function eligibleUsers(): EloquentCollection
    {
        return User::query()
            ->select(['id', 'username', 'full_name', 'email'])
            ->where('is_admin', false)
            ->where('account_status', 'ACTIVE')
            ->orderBy('username')
            ->get();
    }

    /** @return array{voters:int,approved_voters:int,candidates:int,approved_candidates:int} */
    public function participantCounts(Election $election): array
    {
        $candidateQuery = Candidacy::query()->where('election_id', $election->getKey());

        return [
            'voters' => VoterEnrollment::query()->where('election_id', $election->getKey())->count(),
            'approved_voters' => VoterEnrollment::query()
                ->where('election_id', $election->getKey())
                ->where('status', 'APPROVED')
                ->count(),
            // The legacy query counted c.id. Candidacy is keyed by candidate_id.
            'candidates' => (clone $candidateQuery)->distinct()->count('candidate_id'),
            'approved_candidates' => (clone $candidateQuery)
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->distinct()
                ->count('candidate_id'),
        ];
    }

    public function saveVoter(Election $election, int $userId, string $status, int $adminId): void
    {
        $user = $this->eligibleUser($userId);
        $status = strtoupper(trim($status));
        $this->assertStatus($status, self::VOTER_STATUSES, 'voter');

        $enrollment = VoterEnrollment::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'election_id' => $election->getKey(),
        ]);
        $enrollment->status = $status;
        $enrollment->added_by = $adminId;
        $enrollment->save();
    }

    public function changeVoterStatus(Election $election, int $userId, string $status, int $adminId): void
    {
        $status = strtoupper(trim($status));
        $this->assertStatus($status, self::VOTER_STATUSES, 'voter');

        $enrollment = VoterEnrollment::query()
            ->where('user_id', $userId)
            ->where('election_id', $election->getKey())
            ->firstOrFail();
        $enrollment->status = $status;
        $enrollment->added_by = $adminId;
        $enrollment->save();
    }

    public function saveCandidate(Election $election, int $userId, string $status): void
    {
        $user = $this->eligibleUser($userId);
        $status = ucfirst(strtolower(trim($status)));
        $this->assertStatus($status, self::CANDIDATE_STATUSES, 'candidate');

        DB::transaction(function () use ($election, $user, $status): void {
            $candidate = Candidate::query()->find($user->getKey());
            if (! $candidate instanceof Candidate) {
                $candidate = new Candidate;
                $candidate->setAttribute($candidate->getKeyName(), $user->getKey());
                $candidate->save();
            }

            $candidacy = Candidacy::query()->firstOrNew([
                'candidate_id' => $user->getKey(),
                'election_id' => $election->getKey(),
            ]);
            $candidacy->status = $status;
            if (! $candidacy->exists) {
                $candidacy->registered_at = now();
            }
            $candidacy->save();
        });
    }

    public function changeCandidateStatus(Election $election, int $candidateId, string $status): void
    {
        $status = ucfirst(strtolower(trim($status)));
        $this->assertStatus($status, self::CANDIDATE_STATUSES, 'candidate');

        $candidacy = Candidacy::query()
            ->where('candidate_id', $candidateId)
            ->where('election_id', $election->getKey())
            ->firstOrFail();
        $candidacy->status = $status;
        $candidacy->save();
    }

    private function eligibleUser(int $userId): User
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User || (bool) $user->is_admin || ($user->account_status ?? 'ACTIVE') !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'user_id' => 'Choose an active non-administrator user.',
            ]);
        }

        return $user;
    }

    /** @param list<string> $allowed */
    private function assertStatus(string $status, array $allowed, string $subject): void
    {
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Choose a valid {$subject} status.",
            ]);
        }
    }
}
