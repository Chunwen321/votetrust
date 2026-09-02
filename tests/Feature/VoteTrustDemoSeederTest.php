<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoterEnrollment;
use App\Services\ElectionService;
use App\Support\CountryList;
use App\Support\Identity;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Database\Seeders\VoteTrustDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VoteTrustDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_DRAFT = '[Demo] 01 - Editable Public Draft';

    private const PRIVATE_DRAFT = '[Demo] 02 - Private Roster Draft';

    private const PENDING_PAYMENT = '[Demo] 03 - Awaiting Hosting Payment';

    private const PENDING_ADMIN = '[Demo] 04 - Awaiting Administrator Approval';

    private const CANDIDATE_REVIEW = '[Demo] 05 - Candidate Review';

    private const READY_TO_OPEN = '[Demo] 06 - Ready to Open Voting';

    private const PUBLIC_VOTING = '[Demo] 07 - Public Voting Open';

    private const PRIVATE_VOTING = '[Demo] 08 - Private Voting Open';

    private const READY_TO_CLOSE = '[Demo] 09 - Ready to Close';

    private const CLOSED = 'VoteTrust Student Council Election 2026';

    public function test_demo_seed_is_idempotent_and_every_advertised_scenario_is_actionable(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        try {
            $this->seed(VoteTrustDemoSeeder::class);

            $this->assertAdvertisedAccounts();
            $this->assertScenarioGallery($now);

            $firstCounts = $this->seededCounts();
            $firstElectionIds = Election::query()
                ->orderBy('title')
                ->pluck('id', 'title')
                ->all();
            $firstUserIds = User::query()
                ->orderBy('email')
                ->pluck('id', 'email')
                ->all();

            $this->seed(VoteTrustDemoSeeder::class);

            self::assertSame($firstCounts, $this->seededCounts());
            self::assertSame(
                $firstElectionIds,
                Election::query()->orderBy('title')->pluck('id', 'title')->all(),
            );
            self::assertSame(
                $firstUserIds,
                User::query()->orderBy('email')->pluck('id', 'email')->all(),
            );

            $this->assertAdvertisedAccounts();
            $this->assertScenarioGallery($now);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_database_seeder_builds_the_complete_gallery_through_the_common_command_path(): void
    {
        $this->seed();

        self::assertSame(13, User::query()->count());
        self::assertSame(10, Election::query()->count());
        foreach (VoteTrustDemoSeeder::SCENARIO_TITLES as $title) {
            self::assertTrue(Election::query()->where('title', $title)->exists());
        }
    }

    public function test_advertised_accounts_can_log_in_and_reach_their_ready_workspaces(): void
    {
        $this->seed();

        $this->post(route('login.store'), [
            'email' => 'candidateaisha@votetrust.test',
            'password' => 'Candidate!2026',
        ])->assertRedirect(route('dashboard'));
        $candidateElection = Election::query()->where('title', self::CANDIDATE_REVIEW)->firstOrFail();
        $this->get(route('candidate.applications.create', $candidateElection))
            ->assertOk()
            ->assertSee(self::CANDIDATE_REVIEW);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'lowwenkaiorganizer@votetrust.test',
            'password' => 'VoteTrust!2026',
        ])->assertRedirect(route('dashboard'));
        $this->get(route('elections.index'))
            ->assertOk()
            ->assertSee(self::PUBLIC_DRAFT)
            ->assertSee(self::READY_TO_OPEN);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'systemadmin@votetrust.test',
            'password' => 'VoteTrustAdmin!2026',
        ])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.elections.index'))
            ->assertOk()
            ->assertSee(self::PENDING_ADMIN);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'disabledmember@votetrust.test',
            'password' => 'VoteTrustMember!2026',
        ])->assertSessionHas('login_feedback', fn (array $feedback): bool => $feedback['type'] === 'locked'
            && $feedback['title'] === 'Account locked');
        $this->assertGuest();
    }

    public function test_advertised_lifecycle_actions_succeed_without_recreating_the_fixtures(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        try {
            $this->seed(VoteTrustDemoSeeder::class);

            config()->set('election.payment.url', 'https://payments.example.test/api/v1/payments/verify');
            config()->set('election.payment.api_key', 'payment-contract-secret');
            Http::fake(static function (Request $request) {
                return Http::response([
                    'status' => 'S',
                    'message' => 'Hosting payment confirmed.',
                    'requestID' => $request['requestID'],
                    'timestamp' => $request['timestamp'],
                    'data' => [
                        'paymentReference' => 'SEED-TEST-PAID-03',
                        'paymentStatus' => 'CONFIRMED',
                        'amount' => $request['expectedAmount'],
                        'currency' => $request['currency'],
                        'payerId' => $request['payerId'],
                        'electionId' => $request['electionId'],
                        'purpose' => 'ELECTION_HOSTING',
                        'paymentId' => 903,
                    ],
                ]);
            });

            $service = app(ElectionService::class);
            $organizer = User::query()->where('email', 'lowwenkaiorganizer@votetrust.test')->sole();
            $admin = User::query()->where('email', 'systemadmin@votetrust.test')->sole();

            foreach ([self::PUBLIC_DRAFT, self::PRIVATE_DRAFT] as $title) {
                $election = $this->election($title);
                $updated = $service->transition(
                    $organizer,
                    (int) $election->id,
                    'submit_for_payment',
                    'VoteTrust!2026',
                    ['version' => $election->version],
                );
                self::assertSame(Election::PENDING_PAYMENT, $updated->status);
            }

            $pendingPayment = $this->election(self::PENDING_PAYMENT);
            self::assertSame(
                Election::PENDING_ADMIN_APPROVAL,
                $service->transition(
                    $organizer,
                    (int) $pendingPayment->id,
                    'submit_payment',
                    'VoteTrust!2026',
                    ['version' => $pendingPayment->version],
                )->status,
            );

            $pendingApproval = $this->election(self::PENDING_ADMIN);
            self::assertSame(
                Election::NOMINATION_OPEN,
                $service->transition(
                    $admin,
                    (int) $pendingApproval->id,
                    'approve',
                    'VoteTrustAdmin!2026',
                    ['version' => $pendingApproval->version],
                )->status,
            );

            $candidateReview = $this->election(self::CANDIDATE_REVIEW);
            $daniel = User::query()->where('email', 'candidatedaniel@votetrust.test')->sole();
            $danielApplication = Candidacy::query()
                ->where('election_id', $candidateReview->id)
                ->where('user_id', $daniel->id)
                ->sole();
            $candidateReviewVersion = (int) $candidateReview->version;
            self::assertSame(1, $service->reviewCandidates(
                $organizer,
                (int) $candidateReview->id,
                [(int) $danielApplication->id => Candidacy::APPROVED],
                'VoteTrust!2026',
                $candidateReviewVersion,
            ));
            self::assertSame(Candidacy::APPROVED, $danielApplication->refresh()->status);
            self::assertSame($candidateReviewVersion + 1, (int) $candidateReview->refresh()->version);
            self::assertSame(
                'review_candidates',
                collect($candidateReview->transition_history_json)->last()['action'] ?? null,
            );

            $readyToOpen = $this->election(self::READY_TO_OPEN);
            self::assertSame(
                Election::VOTING_OPEN,
                $service->transition(
                    $organizer,
                    (int) $readyToOpen->id,
                    'start_voting',
                    'VoteTrust!2026',
                    ['version' => $readyToOpen->version],
                )->status,
            );

            $readyToClose = $this->election(self::READY_TO_CLOSE);
            self::assertSame(
                Election::CLOSED,
                $service->transition(
                    $organizer,
                    (int) $readyToClose->id,
                    'close_voting',
                    'VoteTrust!2026',
                    ['version' => $readyToClose->version],
                )->status,
            );
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_seeded_voting_scenarios_execute_public_private_and_denied_paths(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        try {
            $this->seed(VoteTrustDemoSeeder::class);
            $this->fakeSeededVotingServices();

            $john = User::query()->where('email', 'voterjohn@votetrust.test')->sole();
            $benjamin = User::query()->where('email', 'voterbenjamin@votetrust.test')->sole();

            foreach ([
                self::PUBLIC_VOTING => 'SEED-PUBLIC-VOTE-01',
                self::PRIVATE_VOTING => 'SEED-PRIVATE-VOTE-01',
            ] as $title => $requestId) {
                $election = $this->election($title);
                $candidate = Candidacy::query()
                    ->where('election_id', $election->id)
                    ->where('status', Candidacy::APPROVED)
                    ->orderBy('id')
                    ->firstOrFail();

                $response = $this->actingAs($john)->post(route('voting.cast', $election), [
                    'candidate_id' => $candidate->user_id,
                    'request_id' => $requestId,
                    'timestamp' => $now->toAtomString(),
                ]);
                $vote = Vote::query()
                    ->where('election_id', $election->id)
                    ->where('voter_id', $john->id)
                    ->sole();

                $response->assertRedirect(route('voting.receipt', $vote));
                self::assertSame((int) $candidate->user_id, (int) $vote->candidate_id);
            }

            $privateElection = $this->election(self::PRIVATE_VOTING);
            $privateCandidate = Candidacy::query()
                ->where('election_id', $privateElection->id)
                ->where('status', Candidacy::APPROVED)
                ->orderBy('id')
                ->firstOrFail();

            $this->actingAs($benjamin)
                ->from(route('voting.ballot', $privateElection))
                ->post(route('voting.cast', $privateElection), [
                    'candidate_id' => $privateCandidate->user_id,
                    'request_id' => 'SEED-PRIVATE-DENIED-01',
                    'timestamp' => $now->toAtomString(),
                ])
                ->assertRedirect(route('voting.ballot', $privateElection))
                ->assertSessionHasErrors('vote');

            self::assertFalse(Vote::query()
                ->where('election_id', $privateElection->id)
                ->where('voter_id', $benjamin->id)
                ->exists());
            Http::assertSentCount(6);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_reseed_recovers_edited_demo_records_and_cleans_candidate_artifacts(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        try {
            $disk = (string) config('candidate.disk', 'local');
            Storage::fake($disk);
            $this->seed(VoteTrustDemoSeeder::class);

            $organizer = User::query()->where('email', 'lowwenkaiorganizer@votetrust.test')->sole();
            $organizerId = (int) $organizer->id;
            $organizer->forceFill(['email' => 'edited.organizer@votetrust.test'])->save();

            // Simulate a renamed row from the previous seeder, which did not
            // assign identity numbers but did retain its account key/name.
            $legacyOrganizer = User::query()->where('email', 'organizeradmin@votetrust.test')->sole();
            $legacyOrganizerId = (int) $legacyOrganizer->id;
            $legacyOrganizer->forceFill([
                'email' => 'edited.legacy.organizer@votetrust.test',
                'ic_number' => null,
            ])->save();

            $draft = $this->election(self::PUBLIC_DRAFT);
            $draftId = (int) $draft->id;
            $draft->forceFill(['title' => 'A teammate renamed this demo draft'])->save();

            $candidate = User::query()->where('email', 'candidateaisha@votetrust.test')->sole();
            $candidateReview = $this->election(self::CANDIDATE_REVIEW);
            $payment = Payment::query()->create([
                'amount' => '10.00',
                'currency' => 'MYR',
                'status' => 'Pending',
                'organizer_id' => $organizerId,
                'candidate_id' => $candidate->id,
            ]);
            $photoPath = trim((string) config('candidate.photos_directory', 'candidate/photos'), '/').'/reseed-photo.jpg';
            Storage::disk($disk)->put($photoPath, 'demo photo');
            $candidacy = Candidacy::query()->create([
                'user_id' => $candidate->id,
                'election_id' => $candidateReview->id,
                'status' => Candidacy::PENDING,
                'registered_at' => $now,
                'manifesto' => 'A temporary application created to verify demo reset cleanup.',
                'candidate_introduction' => 'Temporary seeded-test introduction.',
                'qualifications' => 'Temporary seeded-test qualifications.',
                'reason_for_candidacy' => 'Temporary seeded-test reason.',
                'profile_photo' => $photoPath,
                'profile_photo_mime' => 'image/jpeg',
                'payment_id' => $payment->id,
            ]);
            $this->seed(VoteTrustDemoSeeder::class);

            self::assertSame(
                $organizerId,
                (int) User::query()->where('email', 'lowwenkaiorganizer@votetrust.test')->sole()->id,
            );
            self::assertFalse(User::query()->where('email', 'edited.organizer@votetrust.test')->exists());
            self::assertSame(
                $legacyOrganizerId,
                (int) User::query()->where('email', 'organizeradmin@votetrust.test')->sole()->id,
            );
            self::assertFalse(User::query()->where('email', 'edited.legacy.organizer@votetrust.test')->exists());
            self::assertSame($draftId, (int) $this->election(self::PUBLIC_DRAFT)->id);
            self::assertFalse(Election::query()->where('title', 'A teammate renamed this demo draft')->exists());
            self::assertFalse(Candidacy::query()->whereKey($candidacy->id)->exists());
            self::assertFalse(Payment::query()->whereKey($payment->id)->exists());
            Storage::disk($disk)->assertMissing($photoPath);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    private function assertAdvertisedAccounts(): void
    {
        $accounts = [
            'lowwenkaiorganizer@votetrust.test' => ['VoteTrust!2026', false, 'ACTIVE', true],
            'organizeradmin@votetrust.test' => ['VoteTrust!2026', false, 'ACTIVE', true],
            'systemadmin@votetrust.test' => ['VoteTrustAdmin!2026', true, 'ACTIVE', true],
            'candidateaisha@votetrust.test' => ['Candidate!2026', false, 'ACTIVE', true],
            'candidatedaniel@votetrust.test' => ['Candidate!2026', false, 'ACTIVE', true],
            'voterjohn@votetrust.test' => ['VoteTrustVoter!2026', false, 'ACTIVE', true],
            'voteramina@votetrust.test' => ['VoteTrustVoter!2026', false, 'ACTIVE', true],
            'voterbenjamin@votetrust.test' => ['VoteTrustVoter!2026', false, 'ACTIVE', true],
            'voterchen@votetrust.test' => ['VoteTrustVoter!2026', false, 'ACTIVE', true],
            'voterdevi@votetrust.test' => ['VoteTrustVoter!2026', false, 'ACTIVE', true],
            'voterfarah@votetrust.test' => ['VoteTrustVoter!2026', false, 'ACTIVE', true],
            'unverifiedmember@votetrust.test' => ['VoteTrustMember!2026', false, 'ACTIVE', false],
            'disabledmember@votetrust.test' => ['VoteTrustMember!2026', false, 'DISABLED', true],
        ];

        self::assertSame(count($accounts), User::query()->count());

        foreach ($accounts as $email => [$password, $isAdmin, $accountStatus, $isVerified]) {
            $user = User::query()->where('email', $email)->sole();

            self::assertTrue(Hash::check($password, $user->password), "Unexpected password hash for {$email}.");
            self::assertSame($isAdmin, (bool) $user->is_admin, "Unexpected admin state for {$email}.");
            self::assertSame($accountStatus, $user->account_status, "Unexpected account state for {$email}.");
            self::assertSame($isVerified, (bool) $user->is_verified, "Unexpected verification state for {$email}.");
            self::assertSame($isVerified, $user->verified_at !== null, "Unexpected verified_at value for {$email}.");

            if ($accountStatus === 'DISABLED') {
                self::assertNotNull($user->account_disabled_at, "Disabled account {$email} needs a disabled timestamp.");
            } else {
                self::assertNull($user->account_disabled_at, "Active account {$email} must not have a disabled timestamp.");
            }

            $this->assertCompleteIdentity($user);
        }
    }

    private function assertCompleteIdentity(User $user): void
    {
        $email = (string) $user->email;
        $phoneDigits = preg_replace('/\D+/', '', (string) $user->phone_number) ?? '';
        $hasIc = trim((string) $user->ic_number) !== '';
        $hasPassport = trim((string) $user->passport_number) !== '';

        self::assertNotSame('', trim((string) $user->full_name), "Missing full name for {$email}.");
        self::assertNotNull($user->date_of_birth, "Missing date of birth for {$email}.");
        self::assertTrue(
            CountryList::isValidCode((string) $user->nationality_country_code),
            "Invalid nationality for {$email}.",
        );
        self::assertGreaterThanOrEqual(7, strlen($phoneDigits), "Incomplete phone number for {$email}.");
        self::assertLessThanOrEqual(15, strlen($phoneDigits), "Invalid phone number for {$email}.");
        self::assertNotSame($hasIc, $hasPassport, "{$email} must have exactly one identity number.");

        if ($hasIc) {
            self::assertTrue(Identity::isValidIcNumber((string) $user->ic_number), "Invalid IC for {$email}.");
            $identityDate = Identity::dateOfBirthFromMalaysianIc((string) $user->ic_number);
            self::assertNotNull($identityDate, "Invalid IC birth date for {$email}.");
            self::assertSame($identityDate->toDateString(), $user->date_of_birth->toDateString());
        } else {
            self::assertTrue(
                Identity::isValidPassportNumber((string) $user->passport_number),
                "Invalid passport for {$email}.",
            );
        }
    }

    private function assertScenarioGallery(CarbonImmutable $now): void
    {
        $expectedScenarios = [
            self::PUBLIC_DRAFT => Election::DRAFT,
            self::PRIVATE_DRAFT => Election::DRAFT,
            self::PENDING_PAYMENT => Election::PENDING_PAYMENT,
            self::PENDING_ADMIN => Election::PENDING_ADMIN_APPROVAL,
            self::CANDIDATE_REVIEW => Election::NOMINATION_OPEN,
            self::READY_TO_OPEN => Election::NOMINATION_OPEN,
            self::PUBLIC_VOTING => Election::VOTING_OPEN,
            self::PRIVATE_VOTING => Election::VOTING_OPEN,
            self::READY_TO_CLOSE => Election::VOTING_OPEN,
            self::CLOSED => Election::CLOSED,
        ];

        $actualScenarios = Election::query()->pluck('status', 'title')->all();
        ksort($expectedScenarios);
        ksort($actualScenarios);
        self::assertSame($expectedScenarios, $actualScenarios);

        foreach (Election::query()->get() as $election) {
            self::assertNotNull($election->nomination_start, "Missing nomination start for {$election->title}.");
            self::assertNotNull($election->nomination_end, "Missing nomination end for {$election->title}.");
            self::assertNotNull($election->voting_start, "Missing voting start for {$election->title}.");
            self::assertNotNull($election->voting_end, "Missing voting end for {$election->title}.");
            self::assertTrue($election->nomination_start->lt($election->nomination_end));
            self::assertTrue($election->nomination_end->lte($election->voting_start));
            self::assertTrue($election->voting_start->lt($election->voting_end));
            self::assertGreaterThanOrEqual(1, (int) $election->version);
            self::assertNotEmpty($election->transition_history_json, "Missing audit history for {$election->title}.");
        }

        $this->assertScenarioOwnership();
        $this->assertPendingAdminHandoff($now);
        $this->assertCandidateReviewScenario($now);
        $this->assertReadyToOpenScenario($now);
        $this->assertCurrentVotingScenarios($now);
        $this->assertPrivateRosterConsistency();
        $this->assertEnrollmentAndRosterChronology();
        $this->assertReadyToCloseScenario($now);
        $this->assertClosedVotesTargetApprovedCandidates();
    }

    private function assertScenarioOwnership(): void
    {
        $primaryOrganizer = User::query()->where('email', 'lowwenkaiorganizer@votetrust.test')->sole();
        $secondaryOrganizer = User::query()->where('email', 'organizeradmin@votetrust.test')->sole();

        self::assertSame(2, Organizer::query()->count());
        self::assertTrue(Organizer::query()->whereKey($primaryOrganizer->id)->exists());
        self::assertTrue(Organizer::query()->whereKey($secondaryOrganizer->id)->exists());

        foreach ([
            self::PUBLIC_DRAFT,
            self::PRIVATE_DRAFT,
            self::PENDING_PAYMENT,
            self::CANDIDATE_REVIEW,
            self::READY_TO_OPEN,
            self::PUBLIC_VOTING,
            self::PRIVATE_VOTING,
            self::READY_TO_CLOSE,
        ] as $title) {
            self::assertSame((int) $primaryOrganizer->id, (int) $this->election($title)->organizer_id);
        }

        self::assertSame(
            (int) $secondaryOrganizer->id,
            (int) $this->election(self::PENDING_ADMIN)->organizer_id,
        );
    }

    private function assertPendingAdminHandoff(CarbonImmutable $now): void
    {
        $election = $this->election(self::PENDING_ADMIN);

        self::assertNotNull($election->payment_verified_at);
        self::assertTrue($election->payment_verified_at->lte($now));
        self::assertNotSame('', trim((string) $election->payment_reference));
        self::assertSame('CONFIRMED', $election->payment_provider_status);
        self::assertNull($election->approved_by);
        self::assertNull($election->approved_at);
    }

    private function assertCandidateReviewScenario(CarbonImmutable $now): void
    {
        $election = $this->election(self::CANDIDATE_REVIEW);
        $aisha = User::query()->where('email', 'candidateaisha@votetrust.test')->sole();
        $daniel = User::query()->where('email', 'candidatedaniel@votetrust.test')->sole();

        self::assertTrue($election->nomination_start->lte($now));
        self::assertTrue($election->nomination_end->gte($now));
        self::assertTrue((new ElectionService)->availability($election, $now)['candidateRegistrationAllowed']);
        self::assertFalse(Candidacy::query()
            ->where('election_id', $election->id)
            ->where('user_id', $aisha->id)
            ->exists());
        self::assertTrue(Candidacy::query()
            ->where('election_id', $election->id)
            ->where('user_id', $daniel->id)
            ->where('status', Candidacy::PENDING)
            ->exists());
        $danielApplication = Candidacy::query()
            ->where('election_id', $election->id)
            ->where('user_id', $daniel->id)
            ->sole();
        self::assertNotNull($danielApplication->payment_id);
        self::assertSame('Pending', $danielApplication->payment?->status);
        self::assertGreaterThan(
            0,
            Candidacy::query()->where('election_id', $election->id)->where('status', Candidacy::PENDING)->count(),
        );
        self::assertGreaterThan(
            0,
            Candidacy::query()->where('election_id', $election->id)->where('status', Candidacy::APPROVED)->count(),
        );
        self::assertGreaterThan(
            0,
            Candidacy::query()->where('election_id', $election->id)->where('status', Candidacy::REJECTED)->count(),
        );
    }

    private function assertReadyToOpenScenario(CarbonImmutable $now): void
    {
        $election = $this->election(self::READY_TO_OPEN);
        $summary = (new ElectionService)->candidateSummary($election);

        self::assertTrue($election->nomination_end->lte($now));
        self::assertTrue($election->voting_start->lte($now));
        self::assertTrue($election->voting_end->gt($now));
        self::assertGreaterThan(0, $summary['approved']);
        self::assertSame(0, $summary['pending']);
        self::assertTrue($summary['ready']);
    }

    private function assertCurrentVotingScenarios(CarbonImmutable $now): void
    {
        $john = User::query()->where('email', 'voterjohn@votetrust.test')->sole();
        $amina = User::query()->where('email', 'voteramina@votetrust.test')->sole();
        $service = new ElectionService;

        foreach ([self::PUBLIC_VOTING, self::PRIVATE_VOTING] as $title) {
            $election = $this->election($title);
            $availability = $service->availability($election, $now);

            self::assertTrue($election->voting_start->lte($now));
            self::assertTrue($election->voting_end->gte($now));
            self::assertTrue($availability['votingAllowed']);
            self::assertNotEmpty($availability['ballot']);
            foreach ([$john, $amina] as $approvedVoter) {
                self::assertTrue(VoterEnrollment::query()
                    ->where('election_id', $election->id)
                    ->where('user_id', $approvedVoter->id)
                    ->where('status', VoterEnrollment::APPROVED)
                    ->exists());
            }
            self::assertFalse(Vote::query()
                ->where('election_id', $election->id)
                ->where('voter_id', $john->id)
                ->exists());
            self::assertTrue(Vote::query()
                ->where('election_id', $election->id)
                ->where('voter_id', $amina->id)
                ->exists());
            $this->assertVotesTargetApprovedCandidates($election);
        }
    }

    private function assertPrivateRosterConsistency(): void
    {
        foreach ([self::PRIVATE_DRAFT, self::PRIVATE_VOTING] as $title) {
            $election = $this->election($title);
            $approvedEnrollmentCount = VoterEnrollment::query()
                ->where('election_id', $election->id)
                ->where('status', VoterEnrollment::APPROVED)
                ->count();

            self::assertSame(
                EligibilityRules::PRIVATE,
                EligibilityRules::fromStored($election->eligibility_rules)->mode(),
            );
            self::assertNotNull($election->private_voter_list_uploaded_at);
            self::assertGreaterThan(0, $approvedEnrollmentCount);
            self::assertSame($approvedEnrollmentCount, (int) $election->private_voter_count);

            if ($title === self::PRIVATE_VOTING) {
                $submittedForPaymentAt = collect($election->transition_history_json)
                    ->firstWhere('action', 'submit_for_payment')['created_at'] ?? null;
                self::assertNotNull($submittedForPaymentAt);
                self::assertTrue($election->private_voter_list_uploaded_at->lt(
                    CarbonImmutable::parse((string) $submittedForPaymentAt),
                ));
            }
        }
    }

    private function assertEnrollmentAndRosterChronology(): void
    {
        foreach (Vote::query()->get() as $vote) {
            $enrollment = VoterEnrollment::query()
                ->where('election_id', $vote->election_id)
                ->where('user_id', $vote->voter_id)
                ->sole();

            self::assertNotNull($enrollment->created_at);
            self::assertTrue(
                $enrollment->created_at->lte($vote->cast_at),
                "Voter {$vote->voter_id} was recorded as voting before enrollment in election {$vote->election_id}.",
            );
        }

        foreach ([self::PRIVATE_DRAFT, self::PRIVATE_VOTING] as $title) {
            $election = $this->election($title);

            self::assertNotNull($election->private_voter_list_uploaded_at);
            self::assertTrue($election->private_voter_list_uploaded_at->lt($election->nomination_start));
            foreach (VoterEnrollment::query()->where('election_id', $election->id)->get() as $enrollment) {
                self::assertNotNull($enrollment->created_at);
                self::assertTrue($enrollment->created_at->lte($election->private_voter_list_uploaded_at));
            }
        }
    }

    private function fakeSeededVotingServices(): void
    {
        config([
            'cache.default' => 'array',
            'voting.services.election.base_url' => 'https://election.test',
            'voting.services.election.api_key' => 'election-consumer-key',
            'voting.services.user.base_url' => 'https://user.test',
            'voting.services.user.api_key' => 'user-consumer-key',
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $requestId = (string) $request['requestID'];

            if (str_starts_with($request->url(), 'https://user.test/api/v1/voters/eligibility')) {
                $user = User::query()->findOrFail((int) $request['voterId']);
                $election = Election::query()->findOrFail((int) $request['electionId']);
                $rules = EligibilityRules::fromStored($election->eligibility_rules);
                $enrollment = VoterEnrollment::query()
                    ->where('election_id', $election->id)
                    ->where('user_id', $user->id)
                    ->first();
                $eligible = $user->isActive()
                    && $user->isVerified()
                    && ! $user->isAdmin()
                    && ($rules->mode() === EligibilityRules::PUBLIC
                        || $enrollment?->status === VoterEnrollment::APPROVED);
                $reasonCode = $rules->mode() === EligibilityRules::PUBLIC
                    ? 'PUBLIC_ELECTION'
                    : ($enrollment === null
                        ? 'NO_PRIVATE_ENROLLMENT'
                        : ($eligible ? 'APPROVED' : 'VOTER_'.strtoupper((string) $enrollment->status)));

                return Http::response([
                    'status' => 'S',
                    'message' => 'Voter eligibility retrieved.',
                    'requestID' => $requestId,
                    'timestamp' => CarbonImmutable::now()->toAtomString(),
                    'data' => [
                        'voterId' => (int) $user->id,
                        'electionId' => (int) $election->id,
                        'eligible' => $eligible,
                        'reasonCode' => $reasonCode,
                        'identity' => [
                            'dateOfBirth' => $user->date_of_birth?->toDateString(),
                            'nationality' => ['countryCode' => $user->nationality_country_code],
                        ],
                    ],
                ]);
            }

            if (preg_match('#^https://election\.test/api/v1/elections/(\d+)/availability#', $request->url(), $matches) === 1) {
                $election = Election::query()->findOrFail((int) $matches[1]);

                return Http::response([
                    'status' => 'S',
                    'message' => 'Election availability retrieved.',
                    'requestID' => $requestId,
                    'timestamp' => CarbonImmutable::now()->toAtomString(),
                    'data' => (new ElectionService)->availability($election, CarbonImmutable::now()),
                ]);
            }

            return Http::response([
                'status' => 'E',
                'message' => 'Unexpected seeded test request.',
                'requestID' => $requestId,
                'timestamp' => CarbonImmutable::now()->toAtomString(),
            ], 404);
        });
    }

    private function assertReadyToCloseScenario(CarbonImmutable $now): void
    {
        $election = $this->election(self::READY_TO_CLOSE);
        $availability = (new ElectionService)->availability($election, $now);

        self::assertTrue($election->voting_end->lt($now));
        self::assertFalse($availability['votingAllowed']);
        self::assertGreaterThan(
            0,
            Candidacy::query()
                ->where('election_id', $election->id)
                ->where('status', Candidacy::APPROVED)
                ->count(),
        );
    }

    private function assertClosedVotesTargetApprovedCandidates(): void
    {
        $election = $this->election(self::CLOSED);
        $votes = Vote::query()->where('election_id', $election->id)->get();

        self::assertNotEmpty($votes);
        $this->assertVotesTargetApprovedCandidates($election);
    }

    private function assertVotesTargetApprovedCandidates(Election $election): void
    {
        foreach (Vote::query()->where('election_id', $election->id)->get() as $vote) {
            self::assertTrue(Candidacy::query()
                ->where('election_id', $election->id)
                ->where('user_id', $vote->candidate_id)
                ->where('status', Candidacy::APPROVED)
                ->exists(), "Vote {$vote->id} does not target an approved candidate.");
        }
    }

    /** @return array<string, int> */
    private function seededCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'organizers' => Organizer::query()->count(),
            'payments' => Payment::query()->count(),
            'elections' => Election::query()->count(),
            'candidacies' => Candidacy::query()->count(),
            'voter_enrollments' => VoterEnrollment::query()->count(),
            'votes' => Vote::query()->count(),
        ];
    }

    private function election(string $title): Election
    {
        return Election::query()->where('title', $title)->sole();
    }
}
