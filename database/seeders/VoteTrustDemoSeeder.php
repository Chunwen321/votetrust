<?php

namespace Database\Seeders;

use App\Models\Candidacy;
use App\Models\CandidateDocument;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoterEnrollment;
use App\Services\CandidateRegistration\CandidateFileStorage;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// PIC: Chew Zhexuan — Demo user and voter records.
// PIC: Low Wen Kai — Demo organizer, payment, and election records.
// PIC: Wong Shun Bin — Demo candidate and candidacy records.
// PIC: Goh Chun Wen — Demo voting and results records.
class VoteTrustDemoSeeder extends Seeder
{
    public const SCENARIO_TITLES = [
        'publicDraft' => '[Demo] 01 - Editable Public Draft',
        'privateDraft' => '[Demo] 02 - Private Roster Draft',
        'pendingPayment' => '[Demo] 03 - Awaiting Hosting Payment',
        'pendingApproval' => '[Demo] 04 - Awaiting Administrator Approval',
        'candidateReview' => '[Demo] 05 - Candidate Review',
        'readyToOpen' => '[Demo] 06 - Ready to Open Voting',
        'publicVoting' => '[Demo] 07 - Public Voting Open',
        'privateVoting' => '[Demo] 08 - Private Voting Open',
        'readyToClose' => '[Demo] 09 - Ready to Close',
        'closedResults' => 'VoteTrust Student Council Election 2026',
    ];

    /** @var array<string, array{email:string,full_name:string,password:string,ic_number:string,date_of_birth:string,phone_number:string,is_admin:bool,account_status:string,is_verified:bool}> */
    private const ACCOUNTS = [
        'OrganizerAdmin' => [
            'email' => 'organizeradmin@votetrust.test',
            'full_name' => 'Maya Lim',
            'password' => 'VoteTrust!2026',
            'ic_number' => '960101105001',
            'date_of_birth' => '1996-01-01',
            'phone_number' => '+60 12-600 5001',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'LowWenKaiOrganizer' => [
            'email' => 'lowwenkaiorganizer@votetrust.test',
            'full_name' => 'Low Wen Kai',
            'password' => 'VoteTrust!2026',
            'ic_number' => '970202105002',
            'date_of_birth' => '1997-02-02',
            'phone_number' => '+60 12-600 5002',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'SystemAdmin' => [
            'email' => 'systemadmin@votetrust.test',
            'full_name' => 'VoteTrust System Administrator',
            'password' => 'VoteTrustAdmin!2026',
            'ic_number' => '900303105003',
            'date_of_birth' => '1990-03-03',
            'phone_number' => '+60 12-600 5003',
            'is_admin' => true,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'CandidateAisha' => [
            'email' => 'candidateaisha@votetrust.test',
            'full_name' => 'Aisha Rahman',
            'password' => 'Candidate!2026',
            'ic_number' => '980404105004',
            'date_of_birth' => '1998-04-04',
            'phone_number' => '+60 12-600 5004',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'CandidateDaniel' => [
            'email' => 'candidatedaniel@votetrust.test',
            'full_name' => 'Daniel Tan',
            'password' => 'Candidate!2026',
            'ic_number' => '990505105005',
            'date_of_birth' => '1999-05-05',
            'phone_number' => '+60 12-600 5005',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'VoterJohn' => [
            'email' => 'voterjohn@votetrust.test',
            'full_name' => 'John Lim',
            'password' => 'VoteTrustVoter!2026',
            'ic_number' => '000606105006',
            'date_of_birth' => '2000-06-06',
            'phone_number' => '+60 12-600 5006',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'VoterAmina' => [
            'email' => 'voteramina@votetrust.test',
            'full_name' => 'Amina Yusuf',
            'password' => 'VoteTrustVoter!2026',
            'ic_number' => '010707105007',
            'date_of_birth' => '2001-07-07',
            'phone_number' => '+60 12-600 5007',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'VoterBenjamin' => [
            'email' => 'voterbenjamin@votetrust.test',
            'full_name' => 'Benjamin Lee',
            'password' => 'VoteTrustVoter!2026',
            'ic_number' => '020808105008',
            'date_of_birth' => '2002-08-08',
            'phone_number' => '+60 12-600 5008',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'VoterChen' => [
            'email' => 'voterchen@votetrust.test',
            'full_name' => 'Chen Wei',
            'password' => 'VoteTrustVoter!2026',
            'ic_number' => '030909105009',
            'date_of_birth' => '2003-09-09',
            'phone_number' => '+60 12-600 5009',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'VoterDevi' => [
            'email' => 'voterdevi@votetrust.test',
            'full_name' => 'Devi Nair',
            'password' => 'VoteTrustVoter!2026',
            'ic_number' => '041010105010',
            'date_of_birth' => '2004-10-10',
            'phone_number' => '+60 12-600 5010',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'VoterFarah' => [
            'email' => 'voterfarah@votetrust.test',
            'full_name' => 'Farah Aziz',
            'password' => 'VoteTrustVoter!2026',
            'ic_number' => '051111105011',
            'date_of_birth' => '2005-11-11',
            'phone_number' => '+60 12-600 5011',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => true,
        ],
        'UnverifiedMember' => [
            'email' => 'unverifiedmember@votetrust.test',
            'full_name' => 'Unverified Demo Member',
            'password' => 'VoteTrustMember!2026',
            'ic_number' => '061212105012',
            'date_of_birth' => '2006-12-12',
            'phone_number' => '+60 12-600 5012',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'is_verified' => false,
        ],
        'DisabledMember' => [
            'email' => 'disabledmember@votetrust.test',
            'full_name' => 'Disabled Demo Member',
            'password' => 'VoteTrustMember!2026',
            'ic_number' => '070101105013',
            'date_of_birth' => '2007-01-01',
            'phone_number' => '+60 12-600 5013',
            'is_admin' => false,
            'account_status' => 'DISABLED',
            'is_verified' => true,
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('VoteTrust demo data was not seeded because fixed demo credentials are disabled in production.');

            return;
        }

        $candidatePaths = DB::transaction(function (): array {
            $now = CarbonImmutable::now()->startOfMinute();
            $users = $this->seedUsers($now);

            foreach (['OrganizerAdmin', 'LowWenKaiOrganizer'] as $accountKey) {
                Organizer::query()->updateOrCreate(['user_id' => $users[$accountKey]->id]);
            }

            $elections = $this->seedElections($users, $now);
            $candidatePaths = $this->resetDemoParticipation($elections);
            $this->seedParticipants($users, $elections, $now);

            return $candidatePaths;
        });

        // Delete files only after the database reset and replacement fixtures
        // have committed. A failed transaction must not restore rows whose
        // private files were already removed.
        app(CandidateFileStorage::class)->deleteMany($candidatePaths);
    }

    /** @return array<string, User> */
    private function seedUsers(CarbonImmutable $now): array
    {
        $hashes = [];
        $users = [];

        foreach (self::ACCOUNTS as $accountKey => $account) {
            $verified = $account['is_verified'];
            $password = $account['password'];
            $hashes[$password] ??= Hash::make($password);

            // Identity numbers are not editable through the profile UI, so they
            // remain a stable way to recover a demo persona after its email is
            // changed during a test. Email remains the fallback for upgrading
            // demo rows created by the older seeder before identities existed.
            $user = User::query()->where('ic_number', $account['ic_number'])->first()
                ?? User::query()->where('email', $account['email'])->first()
                ?? User::query()
                    ->where('name', $accountKey)
                    ->whereNull('ic_number')
                    ->where('verification_provider', 'SEEDED')
                    ->first()
                ?? new User;
            $user->forceFill([
                'name' => $accountKey,
                'full_name' => $account['full_name'],
                'ic_number' => $account['ic_number'],
                'passport_number' => null,
                'date_of_birth' => $account['date_of_birth'],
                'nationality_country_code' => 'MY',
                'phone_number' => $account['phone_number'],
                'email' => $account['email'],
                'email_verified_at' => $now->subDays(30),
                'password' => $hashes[$password],
                'is_admin' => $account['is_admin'],
                'account_status' => $account['account_status'],
                'is_verified' => $verified,
                'verified_at' => $verified ? $now->subDays(30) : null,
                'verification_reference' => $verified ? 'SEED-EKYC-'.strtoupper($accountKey) : null,
                'verification_provider' => $verified ? 'SEEDED' : null,
                'verification_session_id' => null,
                'verification_status' => $verified ? 'APPROVED' : 'NOT_STARTED',
                'verification_requested_at' => $verified ? $now->subDays(31) : null,
                'verification_consent_at' => $verified ? $now->subDays(31) : null,
                'verification_last_event_id' => $verified ? 'SEED-'.strtoupper($accountKey) : null,
                'verification_failure_reason' => null,
                'failed_login_attempts' => 0,
                'account_disabled_at' => $account['account_status'] === 'DISABLED' ? $now->subDay() : null,
                'remember_token' => null,
            ])->save();

            $users[$accountKey] = $user->refresh();
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Election>
     */
    private function seedElections(array $users, CarbonImmutable $now): array
    {
        $primaryOrganizer = $users['LowWenKaiOrganizer'];
        $secondaryOrganizer = $users['OrganizerAdmin'];
        $admin = $users['SystemAdmin'];
        $public = EligibilityRules::publicElection(18, 'MY');
        $private = EligibilityRules::privateElection();

        $elections = [];
        $elections['publicDraft'] = $this->seedElection(
            '01',
            self::SCENARIO_TITLES['publicDraft'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::DRAFT,
            $now,
            [
                'position_sought' => 'Student Representative',
                'description' => 'An editable public-election draft for testing configuration changes and the hosting-payment handoff.',
                'nomination_start' => $now->addDay(),
                'nomination_end' => $now->addDays(7),
                'voting_start' => $now->addDays(8),
                'voting_end' => $now->addDays(9),
            ],
        );
        $elections['privateDraft'] = $this->seedElection(
            '02',
            self::SCENARIO_TITLES['privateDraft'],
            $primaryOrganizer,
            $admin,
            $private,
            Election::DRAFT,
            $now,
            [
                'position_sought' => 'Residents Committee Chair',
                'description' => 'A private draft with a seeded roster for testing directory selection, spreadsheet import, and payment submission.',
                'private_voter_list_uploaded_at' => $now->subHour(),
                'nomination_start' => $now->addDay(),
                'nomination_end' => $now->addDays(5),
                'voting_start' => $now->addDays(6),
                'voting_end' => $now->addDays(7),
            ],
        );
        $elections['pendingPayment'] = $this->seedElection(
            '03',
            self::SCENARIO_TITLES['pendingPayment'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::PENDING_PAYMENT,
            $now,
            [
                'position_sought' => 'Faculty Council Delegate',
                'description' => 'A configured election waiting for an organizer to submit a hosting-payment reference.',
                'nomination_start' => $now->addDay(),
                'nomination_end' => $now->addDays(6),
                'voting_start' => $now->addDays(7),
                'voting_end' => $now->addDays(8),
            ],
        );

        $pendingApprovalPayment = $this->verifiedPaymentSnapshot('SEED-HOST-04-PAID', $now->subHours(2));
        $elections['pendingApproval'] = $this->seedElection(
            '04',
            self::SCENARIO_TITLES['pendingApproval'],
            $secondaryOrganizer,
            $admin,
            $public,
            Election::PENDING_ADMIN_APPROVAL,
            $now,
            array_merge([
                'position_sought' => 'Community Programme Lead',
                'description' => 'A paid election awaiting an administrator decision; its verified snapshot avoids requiring the external Payment service.',
                'nomination_start' => $now->subHour(),
                'nomination_end' => $now->addDays(5),
                'voting_start' => $now->addDays(6),
                'voting_end' => $now->addDays(7),
            ], $pendingApprovalPayment),
        );

        $elections['candidateReview'] = $this->seedElection(
            '05',
            self::SCENARIO_TITLES['candidateReview'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::NOMINATION_OPEN,
            $now,
            array_merge([
                'position_sought' => 'Student Welfare Officer',
                'description' => 'Nominations are open with mixed candidate decisions, including one application requiring organizer review.',
                'nomination_start' => $now->subDays(2),
                'nomination_end' => $now->addDays(5),
                'voting_start' => $now->addDays(6),
                'voting_end' => $now->addDays(7),
            ], $this->approvedElectionSnapshot('SEED-HOST-05-PAID', $admin, $now->subDays(2)->subHour())),
        );
        $elections['readyToOpen'] = $this->seedElection(
            '06',
            self::SCENARIO_TITLES['readyToOpen'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::NOMINATION_OPEN,
            $now,
            array_merge([
                'position_sought' => 'Academic Affairs Representative',
                'description' => 'Candidate review is complete and the configured voting start has arrived, so voting can be opened immediately.',
                'nomination_start' => $now->subDays(8),
                'nomination_end' => $now->subHours(2),
                'voting_start' => $now->subHour(),
                'voting_end' => $now->addDays(2),
            ], $this->approvedElectionSnapshot('SEED-HOST-06-PAID', $admin, $now->subDays(8)->subHour())),
        );
        $elections['publicVoting'] = $this->seedElection(
            '07',
            self::SCENARIO_TITLES['publicVoting'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::VOTING_OPEN,
            $now,
            array_merge([
                'position_sought' => 'Campus Sustainability Lead',
                'description' => 'An active public ballot with ready-to-vote and already-voted member personas.',
                'nomination_start' => $now->subDays(10),
                'nomination_end' => $now->subDays(3),
                'voting_start' => $now->subHours(2),
                'voting_end' => $now->addDays(2),
            ], $this->approvedElectionSnapshot('SEED-HOST-07-PAID', $admin, $now->subDays(10)->subHour())),
        );
        $elections['privateVoting'] = $this->seedElection(
            '08',
            self::SCENARIO_TITLES['privateVoting'],
            $primaryOrganizer,
            $admin,
            $private,
            Election::VOTING_OPEN,
            $now,
            array_merge([
                'position_sought' => 'Association Treasurer',
                'description' => 'An active private ballot for testing approved-roster access and denied non-member access.',
                // The roster was populated while this election was still a
                // draft, before its seeded payment-submission audit events.
                'private_voter_list_uploaded_at' => $now->subDays(13)->subHours(12),
                'nomination_start' => $now->subDays(10),
                'nomination_end' => $now->subDays(3),
                'voting_start' => $now->subMinutes(90),
                'voting_end' => $now->addHours(36),
            ], $this->approvedElectionSnapshot('SEED-HOST-08-PAID', $admin, $now->subDays(10)->subHour())),
        );
        $elections['readyToClose'] = $this->seedElection(
            '09',
            self::SCENARIO_TITLES['readyToClose'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::VOTING_OPEN,
            $now,
            array_merge([
                'position_sought' => 'Alumni Network Coordinator',
                'description' => 'Voting has ended while the lifecycle remains open, allowing the organizer to test the close-election action.',
                'nomination_start' => $now->subDays(12),
                'nomination_end' => $now->subDays(6),
                'voting_start' => $now->subDays(3),
                'voting_end' => $now->subHour(),
            ], $this->approvedElectionSnapshot('SEED-HOST-09-PAID', $admin, $now->subDays(12)->subHour())),
        );
        $elections['closedResults'] = $this->seedElection(
            '10',
            self::SCENARIO_TITLES['closedResults'],
            $primaryOrganizer,
            $admin,
            $public,
            Election::CLOSED,
            $now,
            array_merge([
                'position_sought' => 'Student Council Representative',
                'description' => 'A completed election containing stable sample votes for tally and historical-participation demonstrations.',
                'nomination_start' => $now->subDays(35),
                'nomination_end' => $now->subDays(30),
                'voting_start' => $now->subDays(29),
                'voting_end' => $now->subDays(27),
            ], $this->approvedElectionSnapshot('SEED-HOST-10-PAID', $admin, $now->subDays(35)->subHour())),
        );

        return $elections;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedElection(
        string $code,
        string $title,
        User $organizer,
        User $admin,
        EligibilityRules $rules,
        string $status,
        CarbonImmutable $now,
        array $attributes,
    ): Election {
        $base = [
            'organizer_id' => $organizer->getKey(),
            'position_sought' => null,
            'description' => null,
            'eligibility_rules' => $rules->toStorage(),
            'private_voter_list_uploaded_at' => null,
            'private_voter_count' => 0,
            'private_voter_unmatched_count' => 0,
            'hosting_fee' => Election::hostingFeeForMode($rules->mode()),
            'currency' => 'MYR',
            'status' => $status,
            'payment_id' => null,
            'payment_reference' => null,
            'payment_verified_at' => null,
            'payment_request_id' => null,
            'payment_provider_status' => null,
            'payment_response_json' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ];
        $values = array_merge($base, $attributes);
        $history = $this->transitionHistory(
            $code,
            $status,
            (int) $organizer->getKey(),
            (int) $admin->getKey(),
            CarbonImmutable::parse($values['nomination_start']),
            CarbonImmutable::parse($values['voting_start']),
            CarbonImmutable::parse($values['voting_end']),
            $now,
        );
        $values['transition_history_json'] = $history;
        $values['version'] = max(1, count($history));

        // Draft titles are editable. The stable request ID in the seeded audit
        // history lets a rerun recover and reset a renamed demo election.
        $marker = sprintf('SEED-%s-01', $code);
        $election = Election::query()
            ->where('organizer_id', $organizer->getKey())
            ->get()
            ->first(function (Election $candidate) use ($marker, $title): bool {
                if ($candidate->title === $title) {
                    return true;
                }

                return collect($candidate->transition_history_json)
                    ->contains(fn (mixed $record): bool => is_array($record)
                        && ($record['request_id'] ?? null) === $marker);
            }) ?? new Election;
        $election->forceFill(['title' => $title] + $values)->save();

        return $election->refresh();
    }

    /** @return array<string, mixed> */
    private function verifiedPaymentSnapshot(string $reference, CarbonImmutable $verifiedAt): array
    {
        return [
            'payment_reference' => $reference,
            'payment_verified_at' => $verifiedAt,
            'payment_request_id' => 'SEED-VERIFY-'.$reference,
            'payment_provider_status' => 'CONFIRMED',
            'payment_response_json' => [
                'status' => 'S',
                'message' => 'Seeded hosting payment verified for local lifecycle testing.',
                'paymentReference' => $reference,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function approvedElectionSnapshot(string $reference, User $admin, CarbonImmutable $approvedAt): array
    {
        return array_merge($this->verifiedPaymentSnapshot($reference, $approvedAt), [
            'approved_by' => $admin->getKey(),
            'approved_at' => $approvedAt,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function transitionHistory(
        string $code,
        string $status,
        int $organizerId,
        int $adminId,
        CarbonImmutable $nominationStart,
        CarbonImmutable $votingStart,
        CarbonImmutable $votingEnd,
        CarbonImmutable $now,
    ): array {
        $approvalStates = [Election::NOMINATION_OPEN, Election::VOTING_OPEN, Election::CLOSED];
        $paymentStates = [Election::PENDING_ADMIN_APPROVAL, ...$approvalStates];
        $configuredStates = [Election::PENDING_PAYMENT, ...$paymentStates];
        $baseTime = in_array($status, $approvalStates, true)
            ? $nominationStart->subDays(4)
            : $now->subDays(3);
        $history = [
            $this->transitionRecord($code, 1, $organizerId, 'create', 'NONE', Election::DRAFT, $baseTime),
        ];

        if (in_array($status, $configuredStates, true)) {
            $history[] = $this->transitionRecord(
                $code,
                2,
                $organizerId,
                'submit_for_payment',
                Election::DRAFT,
                Election::PENDING_PAYMENT,
                $baseTime->addDay(),
            );
        }
        if (in_array($status, $paymentStates, true)) {
            $history[] = $this->transitionRecord(
                $code,
                3,
                $organizerId,
                'submit_payment',
                Election::PENDING_PAYMENT,
                Election::PENDING_ADMIN_APPROVAL,
                $baseTime->addDays(2),
            );
        }
        if (in_array($status, $approvalStates, true)) {
            $history[] = $this->transitionRecord(
                $code,
                4,
                $adminId,
                'approve',
                Election::PENDING_ADMIN_APPROVAL,
                Election::NOMINATION_OPEN,
                $nominationStart->subHour(),
            );
        }
        if (in_array($status, [Election::VOTING_OPEN, Election::CLOSED], true)) {
            $history[] = $this->transitionRecord(
                $code,
                5,
                $organizerId,
                'start_voting',
                Election::NOMINATION_OPEN,
                Election::VOTING_OPEN,
                $votingStart,
            );
        }
        if ($status === Election::CLOSED) {
            $history[] = $this->transitionRecord(
                $code,
                6,
                $organizerId,
                'close_voting',
                Election::VOTING_OPEN,
                Election::CLOSED,
                $votingEnd,
            );
        }

        return $history;
    }

    /** @return array<string, mixed> */
    private function transitionRecord(
        string $code,
        int $sequence,
        int $actorId,
        string $action,
        string $from,
        string $to,
        CarbonImmutable $at,
    ): array {
        return [
            'actor_user_id' => $actorId,
            'request_id' => sprintf('SEED-%s-%02d', $code, $sequence),
            'action' => $action,
            'from_state' => $from,
            'to_state' => $to,
            'metadata' => ['seeded' => true],
            'created_at' => $at->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, Election>  $elections
     * @return list<string>
     */
    private function resetDemoParticipation(array $elections): array
    {
        $electionIds = array_map(
            static fn (Election $election): int => (int) $election->getKey(),
            array_values($elections),
        );

        $candidacies = Candidacy::query()
            ->with('documents')
            ->whereIn('election_id', $electionIds)
            ->get();
        $candidatePaths = $candidacies
            ->flatMap(function (Candidacy $candidacy): array {
                $paths = $candidacy->documents
                    ->pluck('stored_name')
                    ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
                    ->all();
                if (is_string($candidacy->profile_photo) && $candidacy->profile_photo !== '') {
                    $paths[] = $candidacy->profile_photo;
                }

                return $paths;
            })
            ->unique()
            ->values()
            ->all();
        $candidatePaymentIds = $candidacies
            ->pluck('payment_id')
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        Vote::query()->whereIn('election_id', $electionIds)->delete();
        VoterEnrollment::query()->whereIn('election_id', $electionIds)->delete();
        CandidateDocument::query()
            ->whereIn('candidacy_id', $candidacies->modelKeys())
            ->delete();
        Candidacy::query()->whereIn('election_id', $electionIds)->delete();

        if ($candidatePaymentIds !== []) {
            Payment::query()
                ->whereIn('id', $candidatePaymentIds)
                ->doesntHave('candidacies')
                ->doesntHave('elections')
                ->delete();
        }
        Payment::query()->whereIn('request_id', [
            'SEED-PAYMENT-0001',
            'SEED-CANDIDATE-DANIEL-0001',
        ])->delete();

        return $candidatePaths;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Election>  $elections
     */
    private function seedParticipants(array $users, array $elections, CarbonImmutable $now): void
    {
        $primaryOrganizer = $users['LowWenKaiOrganizer'];
        $admin = $users['SystemAdmin'];

        foreach (['VoterJohn', 'VoterChen', 'VoterDevi'] as $accountKey) {
            $this->seedEnrollment(
                $elections['privateDraft'],
                $users[$accountKey],
                VoterEnrollment::APPROVED,
                VoterEnrollment::SOURCE_PRIVATE_CSV,
                $primaryOrganizer,
                CarbonImmutable::parse($elections['privateDraft']->private_voter_list_uploaded_at),
            );
        }

        $this->seedCandidacy(
            $elections['privateDraft'],
            $users['CandidateAisha'],
            Candidacy::APPROVED,
            $now->subDay(),
        );

        $danielPayment = Payment::query()->updateOrCreate(
            ['request_id' => 'SEED-CANDIDATE-DANIEL-0001'],
            [
                'amount' => config('candidate.deposit_amount', '10.00'),
                'currency' => 'MYR',
                'status' => 'Pending',
                'organizer_id' => $primaryOrganizer->getKey(),
                'candidate_id' => $users['CandidateDaniel']->getKey(),
                'provider_reference' => null,
                'response_json' => null,
                'verified_at' => null,
            ],
        );
        $this->seedCandidacy(
            $elections['candidateReview'],
            $users['CandidateDaniel'],
            Candidacy::PENDING,
            $now->subHours(12),
            $danielPayment,
        );
        $this->seedCandidacy(
            $elections['candidateReview'],
            $users['VoterBenjamin'],
            Candidacy::APPROVED,
            $now->subDay(),
        );
        $this->seedCandidacy(
            $elections['candidateReview'],
            $users['VoterFarah'],
            Candidacy::REJECTED,
            $now->subHours(18),
        );

        foreach (['readyToOpen', 'publicVoting', 'privateVoting', 'readyToClose', 'closedResults'] as $scenario) {
            $registeredAt = CarbonImmutable::parse($elections[$scenario]->nomination_start)->addDay();
            $this->seedCandidacy(
                $elections[$scenario],
                $users['CandidateAisha'],
                Candidacy::APPROVED,
                $registeredAt,
            );
            $this->seedCandidacy(
                $elections[$scenario],
                $users['CandidateDaniel'],
                Candidacy::APPROVED,
                $registeredAt->addHour(),
            );
        }

        $publicEnrollmentAt = CarbonImmutable::parse($elections['publicVoting']->nomination_end)->addHour();
        foreach ([
            ['VoterJohn', VoterEnrollment::APPROVED],
            ['VoterAmina', VoterEnrollment::APPROVED],
        ] as [$accountKey, $status]) {
            $this->seedEnrollment(
                $elections['publicVoting'],
                $users[$accountKey],
                $status,
                VoterEnrollment::SOURCE_MANUAL,
                $admin,
                $publicEnrollmentAt,
            );
        }
        $this->seedVote(
            $elections['publicVoting'],
            $users['VoterAmina'],
            $users['CandidateAisha'],
            $now->subMinutes(30),
        );

        $privateEnrollmentAt = CarbonImmutable::parse($elections['privateVoting']->private_voter_list_uploaded_at);
        foreach ([
            ['VoterJohn', VoterEnrollment::APPROVED],
            ['VoterAmina', VoterEnrollment::APPROVED],
            ['VoterChen', VoterEnrollment::APPROVED],
            ['VoterBenjamin', VoterEnrollment::PENDING],
            ['VoterDevi', VoterEnrollment::SUSPENDED],
        ] as [$accountKey, $status]) {
            $this->seedEnrollment(
                $elections['privateVoting'],
                $users[$accountKey],
                $status,
                VoterEnrollment::SOURCE_PRIVATE_CSV,
                $primaryOrganizer,
                $privateEnrollmentAt,
            );
        }
        $this->seedVote(
            $elections['privateVoting'],
            $users['VoterAmina'],
            $users['CandidateDaniel'],
            $now->subMinutes(20),
        );

        $readyToCloseEnrollmentAt = CarbonImmutable::parse($elections['readyToClose']->voting_start)->subHour();
        foreach (['VoterBenjamin', 'VoterFarah'] as $accountKey) {
            $this->seedEnrollment(
                $elections['readyToClose'],
                $users[$accountKey],
                VoterEnrollment::APPROVED,
                VoterEnrollment::SOURCE_MANUAL,
                $admin,
                $readyToCloseEnrollmentAt,
            );
        }
        $this->seedVote(
            $elections['readyToClose'],
            $users['VoterBenjamin'],
            $users['CandidateAisha'],
            CarbonImmutable::parse($elections['readyToClose']->voting_end)->subHour(),
        );

        $closedVotes = [
            'VoterJohn' => 'CandidateAisha',
            'VoterAmina' => 'CandidateAisha',
            'VoterBenjamin' => 'CandidateDaniel',
            'VoterChen' => 'CandidateAisha',
            'VoterDevi' => 'CandidateDaniel',
            'VoterFarah' => 'CandidateAisha',
        ];
        $closedVotingStart = CarbonImmutable::parse($elections['closedResults']->voting_start);
        $closedEnrollmentAt = $closedVotingStart->subHour();
        $closedVoteIndex = 0;
        foreach ($closedVotes as $voterKey => $candidateKey) {
            $this->seedEnrollment(
                $elections['closedResults'],
                $users[$voterKey],
                VoterEnrollment::APPROVED,
                VoterEnrollment::SOURCE_MANUAL,
                $admin,
                $closedEnrollmentAt,
            );
            $this->seedVote(
                $elections['closedResults'],
                $users[$voterKey],
                $users[$candidateKey],
                $closedVotingStart->addMinutes(10 + ($closedVoteIndex * 5)),
            );
            $closedVoteIndex++;
        }

        $this->syncPrivateVoterCount($elections['privateDraft']);
        $this->syncPrivateVoterCount($elections['privateVoting']);
    }

    private function seedCandidacy(
        Election $election,
        User $candidate,
        string $status,
        CarbonImmutable $registeredAt,
        ?Payment $payment = null,
    ): Candidacy {
        return Candidacy::query()->updateOrCreate(
            ['user_id' => $candidate->getKey(), 'election_id' => $election->getKey()],
            [
                'status' => $status,
                'registered_at' => $registeredAt,
                'manifesto' => 'Transparent decisions, inclusive participation, and measurable progress for every member of our community.',
                'candidate_introduction' => $candidate->display_name.' is committed to practical, accountable community leadership.',
                'qualifications' => 'Experienced in student and community projects, collaborative planning, and responsible budget oversight.',
                'reason_for_candidacy' => 'To turn member feedback into clear priorities and publish regular progress updates throughout the term.',
                'profile_photo' => null,
                'profile_photo_mime' => null,
                'payment_id' => $payment?->getKey(),
            ],
        );
    }

    private function seedEnrollment(
        Election $election,
        User $voter,
        string $status,
        string $source,
        User $addedBy,
        CarbonImmutable $enrolledAt,
    ): VoterEnrollment {
        return VoterEnrollment::query()->updateOrCreate(
            ['user_id' => $voter->getKey(), 'election_id' => $election->getKey()],
            [
                'status' => $status,
                'enrollment_source' => $source,
                'added_by' => $addedBy->getKey(),
                'created_at' => $enrolledAt,
                'updated_at' => $enrolledAt,
            ],
        );
    }

    private function seedVote(
        Election $election,
        User $voter,
        User $candidate,
        CarbonImmutable $castAt,
    ): Vote {
        return Vote::query()->updateOrCreate(
            ['voter_id' => $voter->getKey(), 'election_id' => $election->getKey()],
            ['candidate_id' => $candidate->getKey(), 'cast_at' => $castAt],
        );
    }

    private function syncPrivateVoterCount(Election $election): void
    {
        $election->forceFill([
            'private_voter_count' => VoterEnrollment::query()
                ->where('election_id', $election->getKey())
                ->where('status', VoterEnrollment::APPROVED)
                ->count(),
            'private_voter_unmatched_count' => 0,
        ])->save();
    }
}
