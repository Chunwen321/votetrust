<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Models\Election;
use App\Models\User;
use App\Models\VoterEnrollment;
use App\Support\CountryList;
use Illuminate\Database\Eloquent\Collection;

final class VoterService
{
    /** @return Collection<int, VoterEnrollment> */
    public function enrollmentsForVoter(int $voterId): Collection
    {
        $enrollmentTable = (new VoterEnrollment)->getTable();
        $electionTable = (new Election)->getTable();

        return VoterEnrollment::query()
            ->select([
                "{$enrollmentTable}.*",
                "{$electionTable}.title as election_title",
                "{$electionTable}.status as election_status",
            ])
            ->join($electionTable, "{$electionTable}.id", '=', "{$enrollmentTable}.election_id")
            ->where("{$enrollmentTable}.user_id", $voterId)
            ->orderByDesc("{$enrollmentTable}.updated_at")
            ->orderByDesc("{$enrollmentTable}.election_id")
            ->get();
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
     * @return array{
     *     eligible:bool,
     *     reasonCode:string,
     *     identity:array{dateOfBirth:?string,nationality:array{countryCode:?string}}
     * }
     */
    public function eligibility(int $voterId, int $electionId): array
    {
        Election::query()->findOrFail($electionId);

        $user = User::query()->find($voterId);
        $enrollment = VoterEnrollment::query()
            ->where('user_id', $voterId)
            ->where('election_id', $electionId)
            ->first();
        $identity = $this->identity($user);

        if (! $user instanceof User || ! $enrollment instanceof VoterEnrollment) {
            return ['eligible' => false, 'reasonCode' => 'NO_ELIGIBILITY_RECORD', 'identity' => $identity];
        }

        if ((bool) $user->is_admin) {
            return ['eligible' => false, 'reasonCode' => 'ADMIN_ACCOUNT', 'identity' => $identity];
        }

        if (($user->account_status ?? 'ACTIVE') !== 'ACTIVE') {
            return ['eligible' => false, 'reasonCode' => 'ACCOUNT_INACTIVE', 'identity' => $identity];
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
}
