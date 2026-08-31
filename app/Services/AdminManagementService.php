<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\VoterEnrollment;
use App\Services\User\CandidateModuleClient;
use App\Services\User\ElectionModuleClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminManagementService
{
    /** @var list<string> */
    public const ACCOUNT_STATUSES = ['ACTIVE', 'DISABLED'];

    public function __construct(
        private readonly ElectionModuleClient $elections,
        private readonly CandidateModuleClient $candidates,
    ) {}

    /**
     * @return array{
     *     counts:array{elections:int,active:int,voters:int,pending_candidates:int,members:int,disabled_members:int,pending_election_approvals:int},
     *     recentElections:Collection<int, array{id:int,title:string,status:string,updatedAt:?string,organizerName:string}>,
     *     pendingElectionApprovals:Collection<int, array{id:int,title:string,status:string,updatedAt:?string,organizerName:string}>
     * }
     */
    public function dashboardOverview(): array
    {
        $electionOverview = $this->elections->adminOverview() ?? [
            'counts' => ['elections' => 0, 'active' => 0, 'pendingApprovals' => 0],
            'recentElections' => [],
            'pendingApprovals' => [],
        ];
        $candidateMetrics = $this->candidates->metrics();

        return [
            'counts' => [
                'elections' => $electionOverview['counts']['elections'] ?? 0,
                'active' => $electionOverview['counts']['active'] ?? 0,
                'voters' => VoterEnrollment::query()->where('status', 'APPROVED')->count(),
                'pending_candidates' => $candidateMetrics['pending'] ?? 0,
                'members' => User::query()->where('is_admin', false)->count(),
                'disabled_members' => User::query()->where('is_admin', false)->where('account_status', 'DISABLED')->count(),
                'pending_election_approvals' => $electionOverview['counts']['pendingApprovals'] ?? 0,
            ],
            'recentElections' => $this->withOrganizerNames($electionOverview['recentElections'] ?? []),
            'pendingElectionApprovals' => $this->withOrganizerNames($electionOverview['pendingApprovals'] ?? []),
        ];
    }

    /** @return array{id:int,title:string,status:string,organizerUserId:?int,updatedAt:?string} */
    public function election(int $electionId): array
    {
        $summary = $this->elections->summary($electionId);
        if ($summary === null) {
            throw ValidationException::withMessages(['election' => 'The Election Module could not find this election.']);
        }

        return $summary;
    }

    /** @return EloquentCollection<int, VoterEnrollment> */
    public function voters(int $electionId): EloquentCollection
    {
        $voterTable = (new VoterEnrollment)->getTable();
        $userTable = (new User)->getTable();

        return VoterEnrollment::query()
            ->select([
                "{$voterTable}.*",
                "{$userTable}.full_name",
                "{$userTable}.email",
                "{$userTable}.account_status",
            ])
            ->join($userTable, "{$userTable}.id", '=', "{$voterTable}.user_id")
            ->where("{$voterTable}.election_id", $electionId)
            ->orderByRaw("CASE {$voterTable}.status WHEN 'PENDING' THEN 1 WHEN 'REJECTED' THEN 2 WHEN 'SUSPENDED' THEN 3 ELSE 4 END")
            ->orderBy("{$userTable}.full_name")
            ->orderBy("{$userTable}.email")
            ->get();
    }

    /** @return Collection<int, object{user_id:int,status:string,full_name:?string,email:?string,account_status:?string}> */
    public function candidates(int $electionId): Collection
    {
        $participants = $this->candidates->participants($electionId);
        $users = User::query()->whereIn('id', array_column($participants, 'userId'))->get()->keyBy('id');

        return collect($participants)
            ->map(function (array $participant) use ($users): object {
                $user = $users->get($participant['userId']);

                return (object) [
                    'user_id' => $participant['userId'],
                    'status' => $participant['status'],
                    'full_name' => $user?->full_name,
                    'email' => $user?->email,
                    'account_status' => $user?->account_status,
                ];
            })
            ->sortBy(static fn (object $candidate): string => sprintf('%d-%s', match (strtolower($candidate->status)) {
                'pending' => 1, 'rejected' => 2, default => 3
            }, $candidate->full_name ?? ''))
            ->values();
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
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, self::ACCOUNT_STATUSES, true), function ($query) use ($status): void {
                $query->where('account_status', $status);
            })
            ->orderBy('full_name')
            ->orderBy('email')
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
    public function participantCounts(int $electionId): array
    {
        $candidates = $this->candidates->participants($electionId);

        return [
            'voters' => VoterEnrollment::query()->where('election_id', $electionId)->count(),
            'approved_voters' => VoterEnrollment::query()
                ->where('election_id', $electionId)
                ->where('status', 'APPROVED')
                ->count(),
            'candidates' => count($candidates),
            'approved_candidates' => count(array_filter($candidates, static fn (array $candidate): bool => strtolower($candidate['status']) === 'approved')),
        ];
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

    /**
     * @param  list<array{id:int,title:string,status:string,organizerUserId:?int,updatedAt:?string}>  $elections
     * @return Collection<int, array{id:int,title:string,status:string,updatedAt:?string,organizerName:string}>
     */
    private function withOrganizerNames(array $elections): Collection
    {
        $organizerIds = array_values(array_filter(array_column($elections, 'organizerUserId'), static fn (mixed $id): bool => is_int($id) && $id > 0));
        $organizers = User::query()->whereIn('id', $organizerIds)->get()->keyBy('id');

        return collect($elections)->map(function (array $election) use ($organizers): array {
            $organizer = $election['organizerUserId'] === null ? null : $organizers->get($election['organizerUserId']);

            return [
                'id' => $election['id'],
                'title' => $election['title'],
                'status' => $election['status'],
                'updatedAt' => $election['updatedAt'],
                'organizerName' => $organizer?->display_name ?? $organizer?->email ?? 'Unknown',
            ];
        });
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
