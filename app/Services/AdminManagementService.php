<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\Models\VoterEnrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminManagementService
{
    /** @var list<string> */
    public const VOTER_STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'];

    /** @var list<string> */
    public const CANDIDATE_STATUSES = ['Pending', 'Approved', 'Rejected'];

    /** @var list<string> */
    public const ACCOUNT_STATUSES = ['ACTIVE', 'DISABLED'];

    /** @return array{elections:int,active:int,voters:int,pending_candidates:int,members:int,disabled_members:int,pending_election_approvals:int} */
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
            'members' => User::query()->where('is_admin', false)->count(),
            'disabled_members' => User::query()
                ->where('is_admin', false)
                ->where('account_status', 'DISABLED')
                ->count(),
            'pending_election_approvals' => Election::query()
                ->whereIn('status', [Election::PENDING_FEE_QUOTE, Election::PENDING_ADMIN_APPROVAL])
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

    /** @return EloquentCollection<int, Election> */
    public function pendingElectionApprovals(int $limit = 4): EloquentCollection
    {
        return Election::query()
            ->with('organizer:id,name,username,full_name')
            ->whereIn('status', [Election::PENDING_FEE_QUOTE, Election::PENDING_ADMIN_APPROVAL])
            ->orderBy('updated_at')
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
            ->join($userTable, "{$userTable}.id", '=', "{$candidacyTable}.user_id")
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

    /** @return EloquentCollection<int, User> */
    public function availableVoters(Election $election): EloquentCollection
    {
        return User::query()
            ->select(['id', 'username', 'full_name', 'email'])
            ->where('is_admin', false)
            ->where('account_status', 'ACTIVE')
            ->whereNotIn('id', VoterEnrollment::query()
                ->select('user_id')
                ->where('election_id', $election->getKey()))
            ->orderBy('username')
            ->get();
    }

    /** @return EloquentCollection<int, User> */
    public function availableCandidates(Election $election): EloquentCollection
    {
        return User::query()
            ->select(['id', 'username', 'full_name', 'email'])
            ->where('is_admin', false)
            ->where('account_status', 'ACTIVE')
            ->whereNotIn('id', Candidacy::query()
                ->select('user_id')
                ->where('election_id', $election->getKey()))
            ->orderBy('username')
            ->get();
    }

    /** @return LengthAwarePaginator<int, User> */
    public function members(string $search = '', ?string $status = null): LengthAwarePaginator
    {
        $search = trim($search);
        $status = $status === null ? null : strtoupper(trim($status));

        return User::query()
            ->where('is_admin', false)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($members) use ($search): void {
                    $members
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, self::ACCOUNT_STATUSES, true), function ($query) use ($status): void {
                $query->where('account_status', $status);
            })
            ->orderBy('full_name')
            ->orderBy('username')
            ->paginate(15)
            ->withQueryString();
    }

    /** @return array{active:int,disabled:int} */
    public function accountStatusCounts(): array
    {
        return [
            'active' => User::query()
                ->where('is_admin', false)
                ->where('account_status', 'ACTIVE')
                ->count(),
            'disabled' => User::query()
                ->where('is_admin', false)
                ->where('account_status', 'DISABLED')
                ->count(),
        ];
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
            'candidates' => (clone $candidateQuery)->distinct()->count('user_id'),
            'approved_candidates' => (clone $candidateQuery)
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->distinct()
                ->count('user_id'),
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
            $candidacy = Candidacy::query()->firstOrNew([
                'user_id' => $user->getKey(),
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
            ->where('user_id', $candidateId)
            ->where('election_id', $election->getKey())
            ->firstOrFail();
        $candidacy->status = $status;
        $candidacy->save();
    }

    public function changeMemberAccountStatus(User $administrator, User $member, string $status): void
    {
        $status = strtoupper(trim($status));
        $this->assertStatus($status, self::ACCOUNT_STATUSES, 'account');

        DB::transaction(function () use ($administrator, $member, $status): void {
            /** @var User|null $lockedMember */
            $lockedMember = User::query()->lockForUpdate()->find($member->getKey());

            if (! $lockedMember instanceof User
                || (bool) $lockedMember->is_admin
                || $lockedMember->is($administrator)) {
                throw ValidationException::withMessages([
                    'user' => 'Only non-administrator member accounts can be managed here.',
                ]);
            }

            $lockedMember->forceFill([
                'account_status' => $status,
                'failed_login_attempts' => 0,
                'account_disabled_at' => $status === 'DISABLED'
                    ? ($lockedMember->account_disabled_at ?? now())
                    : null,
            ])->save();
        });
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
