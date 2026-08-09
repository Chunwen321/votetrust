<?php

namespace Database\Seeders;

use App\Models\Candidacy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoterEnrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// PIC: Chew Zhexuan — Demo user and voter records.
// PIC: Low Wen Kai — Demo organizer, payment, and election records.
// PIC: Wong Shun Bin — Demo candidate and candidacy records.
// PIC: Goh Chun Wen — Demo closed-election vote records.
class VoteTrustDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $users = $this->seedUsers();

            foreach (['OrganizerAdmin', 'LowWenKaiOrganizer'] as $username) {
                Organizer::query()->firstOrCreate(['user_id' => $users[$username]->id]);
            }

            foreach (['CandidateAisha', 'CandidateDaniel'] as $username) {
                Candidate::query()->updateOrCreate(
                    ['user_id' => $users[$username]->id],
                    [
                        'full_name' => $users[$username]->display_name,
                        'email' => $users[$username]->email,
                    ],
                );
            }

            $payment = Payment::query()->updateOrCreate(
                ['request_id' => 'SEED-PAYMENT-0001'],
                [
                    'amount' => 100,
                    'currency' => 'MYR',
                    'status' => 'Completed',
                    'organizer_id' => $users['LowWenKaiOrganizer']->id,
                    'provider_reference' => 'VT-HOST-PAID-001',
                    'response_json' => ['status' => 'S', 'message' => 'Seed payment confirmed.'],
                    'verified_at' => '2026-08-01 08:30:00',
                ],
            );

            $election = Election::query()->updateOrCreate(
                ['title' => 'VoteTrust Student Council Election 2026'],
                [
                    'organizer_id' => $users['LowWenKaiOrganizer']->id,
                    'description' => 'A completed election containing stable sample votes for tally demonstrations.',
                    'eligibility_rules' => 'Active students verified by the User Module may participate.',
                    'nomination_start' => '2026-07-30 09:00:00',
                    'nomination_end' => '2026-08-01 09:00:00',
                    'voting_start' => '2026-08-01 10:00:00',
                    'voting_end' => '2026-08-03 18:00:00',
                    'hosting_fee' => 100,
                    'currency' => 'MYR',
                    'status' => Election::CLOSED,
                    'payment_id' => $payment->id,
                    'payment_reference' => 'VT-HOST-PAID-001',
                    'payment_verified_at' => '2026-08-01 08:30:00',
                    'payment_request_id' => 'SEED-PAYMENT-0001',
                    'payment_provider_status' => 'CONFIRMED',
                    'payment_response_json' => ['status' => 'S', 'message' => 'Seed payment confirmed.'],
                    'approved_by' => $users['SystemAdmin']->id,
                    'approved_at' => '2026-08-01 08:45:00',
                    'transition_history_json' => [],
                    'version' => 1,
                ],
            );

            foreach (['CandidateAisha', 'CandidateDaniel'] as $username) {
                Candidacy::query()->updateOrCreate(
                    ['candidate_id' => $users[$username]->id, 'election_id' => $election->id],
                    [
                        'status' => Candidacy::APPROVED,
                        'position_sought' => 'Student Council Representative',
                        'manifesto' => 'A fair, transparent, and inclusive student council for every student.',
                        'registered_at' => $username === 'CandidateAisha'
                            ? '2026-07-31 10:00:00'
                            : '2026-07-31 10:05:00',
                    ],
                );
            }

            $voteChoices = [
                'VoterJohn' => ['CandidateAisha', '2026-08-02 09:00:00'],
                'VoterAmina' => ['CandidateAisha', '2026-08-02 09:05:00'],
                'VoterBenjamin' => ['CandidateDaniel', '2026-08-02 09:10:00'],
                'VoterChen' => ['CandidateAisha', '2026-08-02 09:15:00'],
                'VoterDevi' => ['CandidateDaniel', '2026-08-02 09:20:00'],
                'VoterFarah' => ['CandidateAisha', '2026-08-02 09:25:00'],
            ];

            foreach ($voteChoices as $voterName => [$candidateName, $castAt]) {
                VoterEnrollment::query()->updateOrCreate(
                    ['user_id' => $users[$voterName]->id, 'election_id' => $election->id],
                    ['status' => VoterEnrollment::APPROVED, 'added_by' => $users['SystemAdmin']->id],
                );
                Vote::query()->updateOrCreate(
                    ['voter_id' => $users[$voterName]->id, 'election_id' => $election->id],
                    ['candidate_id' => $users[$candidateName]->id, 'cast_at' => $castAt],
                );
            }
        });
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $accounts = [
            'OrganizerAdmin' => ['VoteTrust!2026', false],
            'LowWenKaiOrganizer' => ['VoteTrust!2026', false],
            'SystemAdmin' => ['VoteTrustAdmin!2026', true],
            'CandidateAisha' => ['Candidate!2026', false],
            'CandidateDaniel' => ['Candidate!2026', false],
            'VoterJohn' => ['VoteTrustVoter!2026', false],
            'VoterAmina' => ['VoteTrustVoter!2026', false],
            'VoterBenjamin' => ['VoteTrustVoter!2026', false],
            'VoterChen' => ['VoteTrustVoter!2026', false],
            'VoterDevi' => ['VoteTrustVoter!2026', false],
            'VoterFarah' => ['VoteTrustVoter!2026', false],
        ];

        $users = [];
        foreach ($accounts as $username => [$password, $isAdmin]) {
            $users[$username] = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $username,
                    'full_name' => trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $username)),
                    'email' => strtolower($username).'@votetrust.test',
                    'password' => Hash::make($password),
                    'date_of_birth' => '2000-01-01',
                    'nationality_country_code' => 'MY',
                    'is_admin' => $isAdmin,
                    'account_status' => 'ACTIVE',
                    'failed_login_attempts' => 0,
                    'account_disabled_at' => null,
                ],
            );
        }

        return $users;
    }
}
