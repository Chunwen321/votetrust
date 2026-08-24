<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\VoterEnrollment;
use App\Services\User\ElectionModuleClient;
use App\Support\CountryList;
use App\Support\Identity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VoterService
{
    /** @var list<string> */
    private const VOTER_VISIBLE_ELECTION_STATES = ['NOMINATION_OPEN', 'VOTING_OPEN', 'CLOSED'];

    public function __construct(private readonly ElectionModuleClient $elections) {}

    /** @return Collection<int, VoterEnrollment> */
    public function enrollmentsForVoter(int $voterId): Collection
    {
        $enrollmentTable = (new VoterEnrollment)->getTable();

        $enrollments = VoterEnrollment::query()
            ->where("{$enrollmentTable}.user_id", $voterId)
            ->orderByDesc("{$enrollmentTable}.updated_at")
            ->orderByDesc("{$enrollmentTable}.election_id")
            ->get()
            ->keyBy(static fn (VoterEnrollment $enrollment): int => (int) $enrollment->election_id);

        $summaries = $this->elections->summaries($enrollments->pluck('election_id')->all());

        $visible = collect();
        $catalogElectionIds = [];
        foreach ($this->elections->voterCatalog() as $election) {
            $electionId = $election['id'];
            $catalogElectionIds[$electionId] = true;

            if ($this->votingHasEnded($election['status'], $election['votingEnd'])) {
                continue;
            }

            $enrollment = $enrollments->get($electionId);
            $isPublic = $election['eligibilityType'] === 'PUBLIC';

            if (! $isPublic && ! $enrollment instanceof VoterEnrollment) {
                continue;
            }

            if (! $enrollment instanceof VoterEnrollment) {
                $enrollment = new VoterEnrollment;
                $enrollment->setAttribute('user_id', $voterId);
                $enrollment->setAttribute('election_id', $electionId);
            }
            if ($isPublic) {
                $enrollment->setAttribute('status', VoterEnrollment::APPROVED);
            }

            $enrollment->setAttribute('election_title', $election['title']);
            $enrollment->setAttribute('election_status', $election['status']);
            $enrollment->setAttribute('election_nomination_start', null);
            $enrollment->setAttribute('election_nomination_end', null);
            $enrollment->setAttribute('election_voting_start', $election['votingStart']);
            $enrollment->setAttribute('election_voting_end', $election['votingEnd']);
            $enrollment->setAttribute('election_voting_allowed', $election['votingAllowed']);
            $enrollment->setAttribute('election_access_type', $election['eligibilityType']);

            $visible->push($enrollment);
        }

        $enrollments
            ->reject(static fn (VoterEnrollment $enrollment): bool => isset($catalogElectionIds[(int) $enrollment->election_id]))
            ->each(function (VoterEnrollment $enrollment) use ($summaries, $visible): void {
                $summary = $summaries[(int) $enrollment->election_id] ?? null;
                $enrollment->setAttribute('election_title', $summary['title'] ?? 'Election #'.$enrollment->election_id);
                $enrollment->setAttribute('election_status', $summary['status'] ?? 'UNAVAILABLE');
                $enrollment->setAttribute('election_nomination_start', $summary['nominationStart'] ?? null);
                $enrollment->setAttribute('election_nomination_end', $summary['nominationEnd'] ?? null);
                $enrollment->setAttribute('election_voting_start', $summary['votingStart'] ?? null);
                $enrollment->setAttribute('election_voting_end', $summary['votingEnd'] ?? null);
                $enrollment->setAttribute('election_voting_allowed', null);
                $enrollment->setAttribute('election_access_type', 'ENROLLED');

                if (in_array($enrollment->election_status, self::VOTER_VISIBLE_ELECTION_STATES, true)
                    && ! $this->votingHasEnded(
                        (string) $enrollment->election_status,
                        $enrollment->election_voting_end,
                    )) {
                    $visible->push($enrollment);
                }
            });

        return $visible->values();
    }

    private function votingHasEnded(string $status, ?string $votingEnd): bool
    {
        if ($status === 'CLOSED') {
            return true;
        }
        if ($votingEnd === null) {
            return false;
        }

        try {
            return CarbonImmutable::now((string) config('app.timezone'))->gt(CarbonImmutable::parse($votingEnd));
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{APPROVED:int,PENDING:int,REJECTED:int,SUSPENDED:int} */
    public function statusCountsForVoter(int $voterId): array
    {
        $counts = ['APPROVED' => 0, 'PENDING' => 0, 'REJECTED' => 0, 'SUSPENDED' => 0];

        VoterEnrollment::query()
            ->where('user_id', $voterId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->each(function (mixed $total, mixed $status) use (&$counts): void {
                $status = (string) $status;
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = (int) $total;
                }
            });

        return $counts;
    }

    /**
     * Adds newly matched spreadsheet rows to a private election. An import
     * never removes existing voters; organisers remove access only through
     * the live directory checkbox.
     *
     * @param  list<array{row:int,fullName:string,identityNumber:string}>  $voters
     * @return array{requested:int,assigned:int,skipped:int,removed:int,totalAssigned:int,unmatchedCount:int,unmatchedRows:list<array{row:int,reason:string}>}
     */
    public function importPrivateRoster(int $electionId, int $organizerUserId, array $voters): array
    {
        $normalisedRows = [];
        $unmatchedRows = [];
        $seen = [];

        foreach ($voters as $voter) {
            $row = (int) ($voter['row'] ?? 0);
            $fullName = $this->normaliseName((string) ($voter['fullName'] ?? ''));
            $identityNumber = $this->normaliseIdentityNumber((string) ($voter['identityNumber'] ?? ''));
            $key = $fullName.'|'.$identityNumber;

            if ($row < 2 || $fullName === '' || $identityNumber === null) {
                $unmatchedRows[] = ['row' => max(2, $row), 'reason' => 'INVALID_IDENTITY_NUMBER'];

                continue;
            }
            if (isset($seen[$key])) {
                $unmatchedRows[] = ['row' => $row, 'reason' => 'DUPLICATE_ROW'];

                continue;
            }

            $seen[$key] = true;
            $normalisedRows[] = ['row' => $row, 'fullName' => $fullName, 'identityNumber' => $identityNumber];
        }

        $users = $this->usersForIdentityNumbers(array_column($normalisedRows, 'identityNumber'));

        $matchedUserIds = [];
        foreach ($normalisedRows as $row) {
            $matches = $this->usersMatchingIdentityNumber($users, $row['identityNumber']);
            if ($matches->isEmpty()) {
                $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'NO_MATCHING_ACCOUNT'];

                continue;
            }
            if ($matches->count() > 1) {
                $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'IDENTITY_NUMBER_AMBIGUOUS'];

                continue;
            }

            $user = $matches->first();
            if ($this->normaliseName((string) $user->full_name) !== $row['fullName']) {
                $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'NAME_DOES_NOT_MATCH_IDENTIFIER'];

                continue;
            }
            if ($user->isAdmin()) {
                $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'ADMIN_ACCOUNT'];

                continue;
            }

            $matchedUserIds[] = (int) $user->getKey();
        }

        $matchedUserIds = array_values(array_unique($matchedUserIds));
        $assigned = 0;
        $skipped = 0;
        $totalAssigned = 0;

        DB::transaction(function () use ($electionId, $organizerUserId, $matchedUserIds, &$assigned, &$skipped, &$totalAssigned): void {
            foreach ($matchedUserIds as $userId) {
                $enrollment = VoterEnrollment::query()->firstOrNew([
                    'user_id' => $userId,
                    'election_id' => $electionId,
                ]);

                if ($enrollment->exists && $enrollment->status === VoterEnrollment::APPROVED) {
                    $skipped++;

                    continue;
                }

                $enrollment->forceFill([
                    'status' => VoterEnrollment::APPROVED,
                    'enrollment_source' => VoterEnrollment::SOURCE_PRIVATE_CSV,
                    'added_by' => $organizerUserId,
                ])->save();
                $assigned++;
            }

            $totalAssigned = VoterEnrollment::query()
                ->where('election_id', $electionId)
                ->where('status', VoterEnrollment::APPROVED)
                ->count();
        });

        return [
            // Count every imported row received, including rows that were rejected
            // during normalisation, so the import summary always reconciles.
            'requested' => count($voters),
            'assigned' => $assigned,
            'skipped' => $skipped,
            'removed' => 0,
            'totalAssigned' => $totalAssigned,
            'unmatchedCount' => count($unmatchedRows),
            'unmatchedRows' => array_slice($unmatchedRows, 0, 100),
        ];
    }

    /**
     * Provides the User/Voter-owned directory required by an organiser to
     * manage a private-election roster. Identity data never crosses directly
     * from the Election module to the user table.
     *
     * @return array{users:list<array{userId:int,fullName:string,identityNumber:string,assigned:bool}>,pagination:array{page:int,perPage:int,total:int,hasMore:bool}}
     */
    public function privateVoterDirectory(int $electionId, string $search, int $page, int $perPage): array
    {
        $search = $this->normaliseIdentitySearch($search);
        $users = User::query()
            ->where('is_admin', false)
            ->where(function ($query): void {
                $query->whereNotNull('ic_number')->orWhereNotNull('passport_number');
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($identityQuery) use ($search): void {
                    $identityQuery
                        ->where('ic_number', 'like', '%'.$search.'%')
                        ->orWhere('passport_number', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'full_name', 'ic_number', 'passport_number'], 'page', $page);

        $assignedIds = VoterEnrollment::query()
            ->where('election_id', $electionId)
            ->where('status', VoterEnrollment::APPROVED)
            ->whereIn('user_id', $users->getCollection()->pluck('id'))
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();

        return [
            'users' => $users->getCollection()
                ->map(static fn (User $user): array => [
                    'userId' => (int) $user->getKey(),
                    'fullName' => (string) $user->full_name,
                    'identityNumber' => (string) ($user->ic_number ?: $user->passport_number),
                    'assigned' => $assignedIds->has((int) $user->getKey()),
                ])
                ->values()
                ->all(),
            'pagination' => [
                'page' => $users->currentPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
                'hasMore' => $users->hasMorePages(),
            ],
        ];
    }

    /** @return array{userId:int,assigned:bool,changed:bool,reasonCode:string,totalAssigned:int} */
    public function setPrivateVoterSelection(int $electionId, int $organizerUserId, int $userId, bool $assigned): array
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User || $user->isAdmin()) {
            return [
                'userId' => $userId,
                'assigned' => false,
                'changed' => false,
                'reasonCode' => 'USER_UNAVAILABLE',
                'totalAssigned' => $this->approvedVoterCount($electionId),
            ];
        }

        $changed = false;
        $reasonCode = $assigned ? 'ALREADY_ASSIGNED' : 'NOT_ASSIGNED';
        DB::transaction(function () use ($electionId, $organizerUserId, $userId, $assigned, &$changed, &$reasonCode): void {
            $enrollment = VoterEnrollment::query()
                ->where('election_id', $electionId)
                ->where('user_id', $userId)
                ->first();

            if (! $assigned) {
                if ($enrollment instanceof VoterEnrollment) {
                    $enrollment->delete();
                    $changed = true;
                    $reasonCode = 'REMOVED';
                }

                return;
            }

            if ($enrollment instanceof VoterEnrollment && $enrollment->status === VoterEnrollment::APPROVED) {
                return;
            }

            ($enrollment ?? new VoterEnrollment([
                'election_id' => $electionId,
                'user_id' => $userId,
            ]))->forceFill([
                'status' => VoterEnrollment::APPROVED,
                'enrollment_source' => VoterEnrollment::SOURCE_ORGANIZER_DIRECTORY,
                'added_by' => $organizerUserId,
            ])->save();
            $changed = true;
            $reasonCode = 'ASSIGNED';
        });

        return [
            'userId' => $userId,
            'assigned' => $assigned,
            'changed' => $changed,
            'reasonCode' => $reasonCode,
            'totalAssigned' => $this->approvedVoterCount($electionId),
        ];
    }

    /**
     * @return array{
     *     eligible:bool,
     *     reasonCode:string,
     *     identity:array{dateOfBirth:?string,nationality:array{countryCode:?string}}
     * }
     */
    public function eligibility(int $voterId, int $electionId): array
    {
        $user = User::query()->find($voterId);
        $identity = $this->identity($user);

        if (! $user instanceof User) {
            return ['eligible' => false, 'reasonCode' => 'NO_ELIGIBILITY_RECORD', 'identity' => $identity];
        }

        if ((bool) $user->is_admin) {
            return ['eligible' => false, 'reasonCode' => 'ADMIN_ACCOUNT', 'identity' => $identity];
        }

        if (($user->account_status ?? 'ACTIVE') !== 'ACTIVE') {
            return ['eligible' => false, 'reasonCode' => 'ACCOUNT_INACTIVE', 'identity' => $identity];
        }

        if (! $user->isVerified()) {
            return ['eligible' => false, 'reasonCode' => 'PROFILE_UNVERIFIED', 'identity' => $identity];
        }

        $eligibility = $this->elections->eligibilityMode($electionId);
        if ($eligibility === null) {
            return ['eligible' => false, 'reasonCode' => 'ELECTION_UNAVAILABLE', 'identity' => $identity];
        }

        if ($eligibility['type'] === 'PUBLIC') {
            return ['eligible' => true, 'reasonCode' => 'PUBLIC_ELECTION', 'identity' => $identity];
        }

        $enrollment = VoterEnrollment::query()
            ->where('user_id', $voterId)
            ->where('election_id', $electionId)
            ->first();
        if (! $enrollment instanceof VoterEnrollment) {
            return ['eligible' => false, 'reasonCode' => 'NO_PRIVATE_ENROLLMENT', 'identity' => $identity];
        }

        $status = strtoupper((string) ($enrollment->status ?? 'PENDING'));
        if ($status !== 'APPROVED') {
            return ['eligible' => false, 'reasonCode' => 'VOTER_'.$status, 'identity' => $identity];
        }

        return ['eligible' => true, 'reasonCode' => 'APPROVED', 'identity' => $identity];
    }

    /** @return array{dateOfBirth:?string,nationality:array{countryCode:?string}} */
    private function identity(?User $user): array
    {
        $countryCode = strtoupper(trim((string) ($user?->nationality_country_code ?? '')));

        return [
            'dateOfBirth' => $user?->date_of_birth?->toDateString(),
            'nationality' => [
                'countryCode' => CountryList::isValidCode($countryCode) ? $countryCode : null,
            ],
        ];
    }

    /** @param list<string> $identityNumbers
     * @return Collection<int, User> */
    private function usersForIdentityNumbers(array $identityNumbers): Collection
    {
        $icNumbers = array_values(array_unique(array_filter(
            $identityNumbers,
            static fn (string $number): bool => Identity::isValidIcNumber($number),
        )));
        $passportNumbers = array_values(array_unique(array_filter(
            array_map(static fn (string $number): string => Identity::normalisePassportNumber($number), $identityNumbers),
            static fn (string $number): bool => Identity::isValidPassportNumber($number),
        )));

        if ($icNumbers === [] && $passportNumbers === []) {
            return collect();
        }

        return User::query()
            ->where(function ($query) use ($icNumbers, $passportNumbers): void {
                if ($icNumbers !== []) {
                    $query->whereIn('ic_number', $icNumbers);
                }
                if ($passportNumbers !== []) {
                    $method = $icNumbers === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('passport_number', $passportNumbers);
                }
            })
            ->get(['id', 'full_name', 'ic_number', 'passport_number', 'is_admin']);
    }

    /** @param Collection<int, User> $users
     * @return Collection<int, User> */
    private function usersMatchingIdentityNumber(Collection $users, string $identityNumber): Collection
    {
        return $users->filter(function (User $user) use ($identityNumber): bool {
            $icNumber = Identity::normaliseIcNumber((string) $user->ic_number);
            $passportNumber = Identity::normalisePassportNumber((string) $user->passport_number);

            return ($icNumber !== '' && hash_equals($icNumber, $identityNumber))
                || ($passportNumber !== '' && hash_equals($passportNumber, $identityNumber));
        });
    }

    private function normaliseIdentityNumber(string $identityNumber): ?string
    {
        $icNumber = Identity::normaliseIcNumber($identityNumber);
        if (Identity::isValidIcNumber($icNumber)) {
            return $icNumber;
        }

        $passportNumber = Identity::normalisePassportNumber($identityNumber);

        return Identity::isValidPassportNumber($passportNumber) ? $passportNumber : null;
    }

    private function normaliseIdentitySearch(string $identityNumber): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($identityNumber)) ?? '');
    }

    private function approvedVoterCount(int $electionId): int
    {
        return VoterEnrollment::query()
            ->where('election_id', $electionId)
            ->where('status', VoterEnrollment::APPROVED)
            ->count();
    }

    private function normaliseName(string $name): string
    {
        return Str::lower(Str::squish($name));
    }
}
