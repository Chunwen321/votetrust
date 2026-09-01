<?php

// PIC: Chew Zhexuan and Low Wen Kai — User/Voter API ownership and Election import flow.

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Models\VoterEnrollment;
use App\Services\ElectionService;
use App\Services\PrivateVoterRosterFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class PrivateVoterRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_user_can_download_the_private_voter_csv_template(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('elections.private-voters.template'))
            ->assertOk()
            ->assertDownload('votetrust-private-voter-template.csv');

        $content = $response->streamedContent();

        self::assertStringContainsString('full_name,ic_number', $content);
        self::assertStringContainsString('050729-14-5232', $content);
        self::assertStringContainsString('E1234567', $content);

        $candidateResponse = $this->actingAs(User::factory()->create())
            ->get(route('elections.private-candidates.template'))
            ->assertOk()
            ->assertDownload('votetrust-private-candidate-template.csv');
        self::assertStringContainsString('full_name,ic_number', $candidateResponse->streamedContent());
    }

    public function test_the_roster_reader_accepts_xlsx_with_ic_or_passport_values(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['full_name', 'ic_number'],
            ['Nur Aisyah Binti Ali', '050729145232'],
            ['Aisha Rahman', 'E1234567'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'votetrust-roster-');
        self::assertNotFalse($path);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $file = new UploadedFile(
                $path,
                'private-voters.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            );
            $expected = [
                ['row' => 2, 'fullName' => 'Nur Aisyah Binti Ali', 'identityNumber' => '050729145232'],
                ['row' => 3, 'fullName' => 'Aisha Rahman', 'identityNumber' => 'E1234567'],
            ];
            $reader = $this->app->make(PrivateVoterRosterFile::class);
            self::assertSame($expected, $reader->parse($file));
            self::assertSame($expected, $reader->parseCandidates($file));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_private_roster_upload_rejects_plain_text_files(): void
    {
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Spreadsheet Only Private Election',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);

        $this->actingAs($organizer)
            ->from(route('elections.show', $election))
            ->post(route('elections.private-voters.import', $election), [
                'voter_list' => UploadedFile::fake()->create('private-voters.txt', 1, 'text/plain'),
                'current_password' => 'password',
            ])
            ->assertRedirect(route('elections.show', $election))
            ->assertSessionHasErrors('voter_list');
    }

    public function test_private_participant_mutations_reject_an_incorrect_current_password_before_saving(): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Protected Participant Election',
            'position_sought' => 'Representative',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::DRAFT,
            'currency' => 'MYR',
            'version' => 1,
        ]);
        Http::fake();

        $this->actingAs($organizer)
            ->post(route('elections.private-voters.import', $election), [
                'voter_list' => UploadedFile::fake()->createWithContent(
                    'private-voters.csv',
                    "full_name,ic_number\nExample Voter,050729145232\n",
                ),
                'current_password' => 'incorrect-password',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
        self::assertNull($election->refresh()->private_voter_list_uploaded_at);

        $this->actingAs($organizer)
            ->patchJson(route('elections.private-candidates.selection', [
                'election' => $election,
                'user' => $candidate,
            ]), [
                'assigned' => true,
                'current_password' => 'incorrect-password',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('candidacy', [
            'election_id' => $election->getKey(),
            'user_id' => $candidate->getKey(),
        ]);
        self::assertSame(1, $election->refresh()->version);
    }

    public function test_user_voter_api_matches_full_name_and_ic_or_passport_and_keeps_existing_import_rows(): void
    {
        config()->set('votetrust-user.api.key', 'private-roster-api-key');

        $organizer = User::factory()->create();
        $matched = User::factory()->create([
            'full_name' => 'Nur Aisyah Binti Ali',
            'ic_number' => '050729145232',
        ]);
        $unmatched = User::factory()->create([
            'full_name' => 'Different Name',
            'ic_number' => '010203101234',
        ]);
        $passportHolder = User::factory()->create([
            'full_name' => 'Aisha Rahman',
            'ic_number' => null,
            'passport_number' => 'E1234567',
            'nationality_country_code' => 'SG',
        ]);
        $election = Election::query()->create(['title' => 'Private Alumni Election']);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->postJson(route('api.voters.private-enrollments'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'voters' => [
                    ['row' => 2, 'fullName' => '  Nur   Aisyah Binti Ali ', 'identityNumber' => '050729-14-5232'],
                    ['row' => 3, 'fullName' => 'Not the registered name', 'identityNumber' => $unmatched->ic_number],
                    ['row' => 4, 'fullName' => 'Aisha Rahman', 'identityNumber' => 'e-123 4567'],
                ],
                'requestID' => 'private-roster-001',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'private-roster-001')
            ->assertJsonPath('data.requested', 3)
            ->assertJsonPath('data.assigned', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.totalAssigned', 2)
            ->assertJsonPath('data.unmatchedCount', 1)
            ->assertJsonPath('data.unmatchedRows.0.row', 3)
            ->assertJsonPath('data.unmatchedRows.0.reason', 'NAME_DOES_NOT_MATCH_IDENTIFIER');

        $this->assertDatabaseHas('voter', [
            'user_id' => $matched->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
            'enrollment_source' => VoterEnrollment::SOURCE_PRIVATE_CSV,
            'added_by' => $organizer->getKey(),
        ]);
        $this->assertDatabaseHas('voter', [
            'user_id' => $passportHolder->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
            'enrollment_source' => VoterEnrollment::SOURCE_PRIVATE_CSV,
        ]);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->postJson(route('api.voters.private-enrollments'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'voters' => [
                    ['row' => 2, 'fullName' => 'Nur Aisyah Binti Ali', 'identityNumber' => '050729145232'],
                    ['row' => 3, 'fullName' => 'Aisha Rahman', 'identityNumber' => 'E1234567'],
                    ['row' => 4, 'fullName' => 'No matching account', 'identityNumber' => '991231145678'],
                ],
                'requestID' => 'private-roster-002',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.requested', 3)
            ->assertJsonPath('data.assigned', 0)
            ->assertJsonPath('data.skipped', 2)
            ->assertJsonPath('data.removed', 0)
            ->assertJsonPath('data.totalAssigned', 2)
            ->assertJsonPath('data.unmatchedCount', 1);

        $this->assertDatabaseHas('voter', [
            'user_id' => $matched->getKey(),
            'election_id' => $election->getKey(),
        ]);
        $this->assertDatabaseHas('voter', [
            'user_id' => $passportHolder->getKey(),
            'election_id' => $election->getKey(),
        ]);
    }

    public function test_organizer_upload_is_processed_by_user_voter_api_without_storing_the_csv(): void
    {
        config()->set('election.user_service.base_url', 'https://user.test');
        config()->set('election.user_service.api_key', 'user-module-key');

        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Private Faculty Election',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);

        Http::fake(function (ClientRequest $request) use ($election): mixed {
            if ($request->method() === 'POST'
                && $request->url() === 'https://user.test/api/v1/voters/private-enrollments') {
                return Http::response([
                    'status' => 'S',
                    'message' => 'Private voter roster processed.',
                    'requestID' => $request['requestID'],
                    'timestamp' => now()->toAtomString(),
                    'data' => [
                        'electionId' => $election->getKey(),
                        'requested' => 2,
                        'assigned' => 1,
                        'skipped' => 0,
                        'removed' => 0,
                        'totalAssigned' => 1,
                        'unmatchedCount' => 1,
                        'unmatchedRows' => [['row' => 3, 'reason' => 'NO_MATCHING_ACCOUNT']],
                    ],
                ]);
            }

            return Http::response(['status' => 'E'], 404);
        });

        $this->actingAs($organizer)
            ->post(route('elections.private-voters.import', $election), [
                'voter_list' => UploadedFile::fake()->createWithContent(
                    'private-voters.csv',
                    "full_name,ic_number\nNur Aisyah Binti Ali,050729145232\nUnknown Member,991231145678\n",
                ),
                'current_password' => 'password',
            ])
            ->assertRedirect(route('elections.show', $election))
            ->assertSessionHas('success', 'Private voter list processed: 1 new voter(s) assigned, 1 row(s) did not match a registered user.');

        $election->refresh();
        self::assertNotNull($election->private_voter_list_uploaded_at);
        self::assertSame(1, $election->private_voter_count);
        self::assertSame(1, $election->private_voter_unmatched_count);
        self::assertTrue(collect($election->transition_history_json)->contains(
            fn (array $entry): bool => ($entry['action'] ?? null) === 'import_private_voter_list',
        ));

        Http::assertSent(function (ClientRequest $request) use ($election, $organizer): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://user.test/api/v1/voters/private-enrollments'
                && $request->hasHeader('X-Internal-Api-Key', 'user-module-key')
                && $request['electionId'] === $election->getKey()
                && $request['organizerUserId'] === $organizer->getKey()
                && $request['voters'] === [
                    ['row' => 2, 'fullName' => 'Nur Aisyah Binti Ali', 'identityNumber' => '050729145232'],
                    ['row' => 3, 'fullName' => 'Unknown Member', 'identityNumber' => '991231145678'],
                ];
        });
    }

    public function test_user_voter_api_searches_the_private_voter_directory_and_saves_checkbox_selection(): void
    {
        config()->set('votetrust-user.api.key', 'private-roster-api-key');

        $organizer = User::factory()->create();
        $directoryVoter = User::factory()->create([
            'full_name' => 'Directory Member',
            'ic_number' => '050729145232',
        ]);
        $passportHolder = User::factory()->create([
            'full_name' => 'Passport Member',
            'ic_number' => null,
            'passport_number' => 'P7654321',
            'nationality_country_code' => 'SG',
        ]);
        $unverifiedVoter = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Unverified Directory Member',
            'ic_number' => '060606145678',
        ]);
        $disabledVoter = User::factory()->create([
            'full_name' => 'Disabled Directory Member',
            'ic_number' => '070707145678',
            'account_status' => 'DISABLED',
            'account_disabled_at' => now(),
        ]);
        User::factory()->create([
            'full_name' => 'System Administrator',
            'ic_number' => '990101145678',
            'is_admin' => true,
        ]);
        $election = Election::query()->create(['title' => 'Private Directory Election']);
        self::assertTrue($unverifiedVoter->isActive());
        self::assertFalse($unverifiedVoter->isVerified());

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->getJson(route('api.voters.private-directory', [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'search' => '050729',
                'page' => 1,
                'perPage' => 25,
                'requestID' => 'private-directory-001',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.users.0.userId', $directoryVoter->getKey())
            ->assertJsonPath('data.users.0.fullName', 'Directory Member')
            ->assertJsonPath('data.users.0.assigned', false)
            ->assertJsonPath('data.pagination.total', 1);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->patchJson(route('api.voters.private-enrollments.selection'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'userId' => $directoryVoter->getKey(),
                'assigned' => true,
                'requestID' => 'private-directory-002',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.totalAssigned', 1);

        $this->assertDatabaseHas('voter', [
            'user_id' => $directoryVoter->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
            'enrollment_source' => VoterEnrollment::SOURCE_ORGANIZER_DIRECTORY,
        ]);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->patchJson(route('api.voters.private-enrollments.selection'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'userId' => $directoryVoter->getKey(),
                'assigned' => false,
                'requestID' => 'private-directory-003',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', false)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.totalAssigned', 0);

        $this->assertDatabaseMissing('voter', [
            'user_id' => $directoryVoter->getKey(),
            'election_id' => $election->getKey(),
        ]);
        $this->assertDatabaseMissing('voter', [
            'user_id' => $passportHolder->getKey(),
            'election_id' => $election->getKey(),
        ]);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->getJson(route('api.voters.private-directory', [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'search' => '060606145678',
                'page' => 1,
                'perPage' => 25,
                'requestID' => 'private-directory-004',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.userId', $unverifiedVoter->getKey())
            ->assertJsonPath('data.users.0.verified', false);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->patchJson(route('api.voters.private-enrollments.selection'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'userId' => $unverifiedVoter->getKey(),
                'assigned' => true,
                'requestID' => 'private-directory-005',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', true)
            ->assertJsonPath('data.totalAssigned', 1);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->getJson(route('api.voters.private-directory', [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'search' => '070707145678',
                'page' => 1,
                'perPage' => 25,
                'requestID' => 'private-directory-006',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->patchJson(route('api.voters.private-enrollments.selection'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'userId' => $disabledVoter->getKey(),
                'assigned' => true,
                'requestID' => 'private-directory-007',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', false)
            ->assertJsonPath('data.reasonCode', 'USER_UNAVAILABLE');

        $this->assertDatabaseHas('voter', [
            'user_id' => $unverifiedVoter->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);
        $this->assertDatabaseMissing('voter', [
            'user_id' => $disabledVoter->getKey(),
            'election_id' => $election->getKey(),
        ]);
    }

    public function test_private_voter_import_accepts_unverified_members_but_rejects_disabled_accounts(): void
    {
        config()->set('votetrust-user.api.key', 'private-roster-api-key');

        $organizer = User::factory()->create();
        $unverifiedVoter = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Unverified Import Member',
            'ic_number' => '080808145678',
        ]);
        $disabledVoter = User::factory()->create([
            'full_name' => 'Disabled Import Member',
            'ic_number' => '090909145678',
            'account_status' => 'DISABLED',
            'account_disabled_at' => now(),
        ]);
        $election = Election::query()->create(['title' => 'Unverified Voter Import']);

        $this->withHeader('X-Internal-Api-Key', 'private-roster-api-key')
            ->postJson(route('api.voters.private-enrollments'), [
                'electionId' => $election->getKey(),
                'organizerUserId' => $organizer->getKey(),
                'voters' => [
                    ['row' => 2, 'fullName' => 'Unverified Import Member', 'identityNumber' => '080808145678'],
                    ['row' => 3, 'fullName' => 'Disabled Import Member', 'identityNumber' => '090909145678'],
                ],
                'requestID' => 'private-import-verification-001',
                'timestamp' => now()->toAtomString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', 1)
            ->assertJsonPath('data.unmatchedCount', 1)
            ->assertJsonPath('data.unmatchedRows.0.reason', 'ACCOUNT_INACTIVE');

        $this->assertDatabaseHas('voter', [
            'user_id' => $unverifiedVoter->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);
        $this->assertDatabaseMissing('voter', [
            'user_id' => $disabledVoter->getKey(),
            'election_id' => $election->getKey(),
        ]);
    }

    public function test_organizer_can_manage_private_voters_live_while_nominations_are_open(): void
    {
        config()->set('election.user_service.base_url', 'https://user.test');
        config()->set('election.user_service.api_key', 'user-module-key');

        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Manual Private Assignment Election',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
            'nomination_start' => now()->subHour(),
            'nomination_end' => now()->addDay(),
            'voting_start' => now()->addDays(2),
            'voting_end' => now()->addDays(3),
        ]);

        Http::fake(function (ClientRequest $request) use ($election): mixed {
            if ($request->method() === 'GET'
                && str_starts_with($request->url(), 'https://user.test/api/v1/voters/private-directory?')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response([
                    'status' => 'S',
                    'requestID' => $query['requestID'] ?? 'UNKNOWN',
                    'timestamp' => now()->toAtomString(),
                    'data' => [
                        'electionId' => $election->getKey(),
                        'users' => [[
                            'userId' => 42,
                            'fullName' => 'Directory Voter',
                            'identityNumber' => '050729145232',
                            'verified' => true,
                            'assigned' => false,
                        ]],
                        'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 1, 'hasMore' => false],
                    ],
                ]);
            }

            if ($request->method() === 'PATCH'
                && $request->url() === 'https://user.test/api/v1/voters/private-enrollments/selection') {
                return Http::response([
                    'status' => 'S',
                    'requestID' => $request['requestID'],
                    'timestamp' => now()->toAtomString(),
                    'data' => [
                        'electionId' => $election->getKey(),
                        'userId' => 42,
                        'assigned' => true,
                        'changed' => true,
                        'reasonCode' => 'ASSIGNED',
                        'totalAssigned' => 2,
                    ],
                ]);
            }

            return Http::response(['status' => 'E'], 404);
        });

        $this->actingAs($organizer)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSeeText('Add voters to this election')
            ->assertSeeText('Choose voters from your members')
            ->assertSeeText('Import a voter list')
            ->assertSeeText('Download CSV template')
            ->assertSeeText('Choose file')
            ->assertSeeText('Nomination period')
            ->assertSeeText('Voting period')
            ->assertSee('data-file-upload', false)
            ->assertSee('id="voter_list"', false)
            ->assertSee('class="election-file-upload__native"', false)
            ->assertSee('aria-required="true" hidden', false);

        $this->actingAs($organizer)
            ->getJson(route('elections.private-voters.directory', ['election' => $election, 'search' => '050729']))
            ->assertOk()
            ->assertJsonPath('data.users.0.fullName', 'Directory Voter')
            ->assertJsonPath('data.users.0.assigned', false);

        $this->actingAs($organizer)
            ->patchJson(route('elections.private-voters.selection', ['election' => $election, 'user' => 42]), [
                'assigned' => true,
                'current_password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', true)
            ->assertJsonPath('data.totalAssigned', 2)
            ->assertJsonPath('data.version', 2);

        $election->refresh();
        self::assertNotNull($election->private_voter_list_uploaded_at);
        self::assertSame(2, $election->private_voter_count);
        self::assertTrue(collect($election->transition_history_json)->contains(
            fn (array $entry): bool => ($entry['action'] ?? null) === 'assign_private_voter'
                && ($entry['from_state'] ?? null) === Election::NOMINATION_OPEN
                && ($entry['to_state'] ?? null) === Election::NOMINATION_OPEN,
        ));

        Http::assertSent(function (ClientRequest $request) use ($election, $organizer): bool {
            return $request->method() === 'PATCH'
                && $request->url() === 'https://user.test/api/v1/voters/private-enrollments/selection'
                && $request->hasHeader('X-Internal-Api-Key', 'user-module-key')
                && $request['electionId'] === $election->getKey()
                && $request['organizerUserId'] === $organizer->getKey()
                && $request['userId'] === 42
                && $request['assigned'] === true;
        });
    }

    public function test_private_election_requires_a_candidate_but_not_voters_before_payment(): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create();
        $service = $this->app->make(ElectionService::class);
        $election = $service->create($organizer, [
            'title' => 'Private Payment Gate Election',
            'position_sought' => 'Class Representative',
            'description' => 'A private election must receive its candidate assignment before payment.',
            'eligibility_mode' => 'PRIVATE',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
        ]);

        try {
            $service->transition($organizer, $election->getKey(), 'submit_for_payment', 'password', ['version' => 1]);
            self::fail('A private election continued to payment without a candidate.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('Assign at least one approved candidate', $exception->getMessage());
        }

        $assignment = $service->assignPrivateCandidate(
            $organizer,
            (int) $election->getKey(),
            (int) $candidate->getKey(),
            'password',
            1,
        );

        self::assertSame(Candidacy::APPROVED, $assignment->status);
        self::assertNull($election->refresh()->private_voter_list_uploaded_at);

        $updated = $service->transition($organizer, $election->getKey(), 'submit_for_payment', 'password', ['version' => 2]);
        self::assertSame(Election::PENDING_PAYMENT, $updated->status);
        self::assertSame(0, $updated->private_voter_count);
    }

    public function test_organizer_can_search_and_toggle_private_candidate_assignments(): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create([
            'full_name' => 'Searchable Candidate',
            'email' => 'searchable-candidate@example.test',
            'ic_number' => '050729145232',
        ]);
        $unverifiedCandidate = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Unverified Candidate',
            'ic_number' => '101010145678',
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Searchable Candidate Assignment',
            'position_sought' => 'Class Representative',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::PENDING_ADMIN_APPROVAL,
            'currency' => 'MYR',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'version' => 1,
        ]);

        $this->actingAs($organizer)
            ->getJson(route('elections.private-candidates.directory', [
                'election' => $election,
                'search' => '050729-14-5232',
            ]))
            ->assertOk()
            ->assertJsonPath('data.users.0.userId', $candidate->getKey())
            ->assertJsonPath('data.users.0.fullName', 'Searchable Candidate')
            ->assertJsonPath('data.users.0.assigned', false);

        $this->actingAs($organizer)
            ->getJson(route('elections.private-candidates.directory', [
                'election' => $election,
                'search' => '101010145678',
            ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->actingAs($organizer)
            ->patchJson(route('elections.private-candidates.selection', [
                'election' => $election,
                'user' => $unverifiedCandidate,
            ]), ['assigned' => true, 'current_password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This member is no longer available for candidate assignment.');

        $this->actingAs($organizer)
            ->patchJson(route('elections.private-candidates.selection', [
                'election' => $election,
                'user' => $candidate,
            ]), ['assigned' => true, 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.assigned', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.totalAssigned', 1)
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('candidacy', [
            'election_id' => $election->getKey(),
            'user_id' => $candidate->getKey(),
            'status' => Candidacy::APPROVED,
        ]);

        $this->actingAs($organizer)
            ->patchJson(route('elections.private-candidates.selection', [
                'election' => $election,
                'user' => $candidate,
            ]), ['assigned' => false, 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.assigned', false)
            ->assertJsonPath('data.totalAssigned', 0)
            ->assertJsonPath('data.version', 3);

        $this->assertDatabaseHas('candidacy', [
            'election_id' => $election->getKey(),
            'user_id' => $candidate->getKey(),
            'status' => Candidacy::REJECTED,
        ]);
        self::assertSame([
            'assign_private_candidate',
            'remove_private_candidate',
        ], collect($election->refresh()->transition_history_json)->pluck('action')->all());
    }

    public function test_organizer_can_import_a_private_candidate_spreadsheet_without_removing_existing_candidates(): void
    {
        $organizer = User::factory()->create();
        $existingCandidate = User::factory()->create();
        $importedCandidate = User::factory()->create([
            'full_name' => 'Imported Candidate',
            'passport_number' => 'A1234567',
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Candidate Spreadsheet Election',
            'position_sought' => 'Class Representative',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::DRAFT,
            'currency' => 'MYR',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'version' => 1,
        ]);
        Candidacy::query()->create([
            'election_id' => $election->getKey(),
            'user_id' => $existingCandidate->getKey(),
            'status' => Candidacy::APPROVED,
            'registered_at' => now(),
        ]);

        $this->actingAs($organizer)
            ->post(route('elections.private-candidates.import', $election), [
                'candidate_list' => UploadedFile::fake()->createWithContent(
                    'private-candidates.csv',
                    "full_name,ic_number\nImported Candidate,A1234567\nUnknown Candidate,B7654321\n",
                ),
                'current_password' => 'password',
            ])
            ->assertRedirect(route('elections.show', $election).'#candidates')
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 new candidate(s) assigned')
                && str_contains($message, '1 row(s) did not match'));

        $this->assertDatabaseHas('candidacy', [
            'election_id' => $election->getKey(),
            'user_id' => $existingCandidate->getKey(),
            'status' => Candidacy::APPROVED,
        ]);
        $this->assertDatabaseHas('candidacy', [
            'election_id' => $election->getKey(),
            'user_id' => $importedCandidate->getKey(),
            'status' => Candidacy::APPROVED,
        ]);
        self::assertSame('import_private_candidate_list', collect($election->refresh()->transition_history_json)->last()['action'] ?? null);
    }

    public function test_organizer_can_manage_candidates_and_voters_together_during_admin_review(): void
    {
        config()->set('election.user_service.base_url', 'https://user.test');
        config()->set('election.user_service.api_key', 'user-module-key');

        $organizer = User::factory()->create();
        $candidate = User::factory()->create(['full_name' => 'Review Stage Candidate']);
        $laterCandidate = User::factory()->create(['full_name' => 'Locked Stage Candidate']);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Participants During Admin Review',
            'position_sought' => 'Class Representative',
            'eligibility_rules' => json_encode(['type' => 'PRIVATE']),
            'status' => Election::PENDING_ADMIN_APPROVAL,
            'currency' => 'MYR',
            'payment_reference' => 'PAY-PARTICIPANTS-01',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'version' => 1,
        ]);

        Http::fake(fn (ClientRequest $request) => Http::response([
            'status' => 'S',
            'requestID' => $request['requestID'] ?? 'PARTICIPANT-DIRECTORY',
            'timestamp' => now()->toAtomString(),
            'data' => [
                'electionId' => $election->getKey(),
                'users' => [],
                'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0, 'hasMore' => false],
            ],
        ]));

        $this->actingAs($organizer)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSeeText('Assign candidates to the ballot')
            ->assertSeeText('Add voters to this election')
            ->assertSee('data-private-voter-directory', false)
            ->assertSee('data-private-candidate-directory', false)
            ->assertSee('data-participant-password', false)
            ->assertSee('name="current_password"', false)
            ->assertSeeText('It is cleared after every attempt.')
            ->assertSee('name="candidate_list"', false);

        $this->actingAs($organizer)
            ->getJson(route('elections.private-voters.directory', $election))
            ->assertOk();

        $this->actingAs($organizer)
            ->post(route('elections.candidates.assign', $election), [
                'user_id' => $candidate->getKey(),
                'current_password' => 'password',
                'version' => 1,
            ])
            ->assertRedirect(route('elections.show', $election).'#candidates');

        self::assertSame(Candidacy::APPROVED, Candidacy::query()
            ->where('election_id', $election->getKey())
            ->where('user_id', $candidate->getKey())
            ->value('status'));

        $election->forceFill(['status' => Election::VOTING_OPEN])->save();

        $this->actingAs($organizer)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSeeText('Review Stage Candidate')
            ->assertSeeText('Private voter list')
            ->assertDontSee('data-private-voter-directory', false)
            ->assertDontSee('data-private-candidate-directory', false)
            ->assertDontSee('name="candidate_list"', false);

        $this->actingAs($organizer)
            ->from(route('elections.show', $election))
            ->post(route('elections.candidates.assign', $election), [
                'user_id' => $laterCandidate->getKey(),
                'current_password' => 'password',
                'version' => 2,
            ])
            ->assertRedirect(route('elections.show', $election))
            ->assertSessionHas('error', 'Private candidates can be assigned until voting begins.');

        $this->assertDatabaseMissing('candidacy', [
            'election_id' => $election->getKey(),
            'user_id' => $laterCandidate->getKey(),
        ]);
    }

    public function test_organizer_can_assign_and_edit_private_candidates_during_the_nomination_workflow(): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create(['full_name' => 'Assigned Candidate']);
        $service = $this->app->make(ElectionService::class);
        $election = $service->create($organizer, [
            'title' => 'Private Candidate Assignment Election',
            'position_sought' => 'Class Representative',
            'description' => 'A private election used to verify organizer candidate assignment controls.',
            'eligibility_mode' => 'PRIVATE',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
        ]);

        $this->actingAs($organizer)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSeeText('Assign candidates to the ballot')
            ->assertSeeText('Voters may be assigned later')
            ->assertSeeText('Choose candidates from your members')
            ->assertSeeText('Import a candidate list');

        $this->actingAs($organizer)
            ->post(route('elections.candidates.assign', $election), [
                'user_id' => $candidate->getKey(),
                'current_password' => 'password',
                'version' => 1,
            ])
            ->assertRedirect(route('elections.show', $election).'#candidates');

        $candidacy = Candidacy::query()
            ->where('election_id', $election->getKey())
            ->where('user_id', $candidate->getKey())
            ->sole();
        self::assertSame(Candidacy::APPROVED, $candidacy->status);
        self::assertSame('assign_candidate', collect($election->refresh()->transition_history_json)->last()['action'] ?? null);

        $election->forceFill(['status' => Election::NOMINATION_OPEN])->save();

        $this->actingAs($organizer)
            ->post(route('elections.candidates.review', $election), [
                'decisions' => [$candidacy->getKey() => Candidacy::REJECTED],
                'current_password' => 'password',
                'version' => 2,
            ])
            ->assertRedirect(route('elections.show', $election));

        self::assertSame(Candidacy::REJECTED, $candidacy->refresh()->status);
    }
}
