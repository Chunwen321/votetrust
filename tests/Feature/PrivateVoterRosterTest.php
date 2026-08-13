<?php

// PIC: Chew Zhexuan and Low Wen Kai — User/Voter API ownership and Election import flow.

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
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
            self::assertSame([
                ['row' => 2, 'fullName' => 'Nur Aisyah Binti Ali', 'identityNumber' => '050729145232'],
                ['row' => 3, 'fullName' => 'Aisha Rahman', 'identityNumber' => 'E1234567'],
            ], $this->app->make(PrivateVoterRosterFile::class)->parse($file));
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
            ])
            ->assertRedirect(route('elections.show', $election))
            ->assertSessionHasErrors('voter_list');
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
        User::factory()->create([
            'full_name' => 'System Administrator',
            'ic_number' => '990101145678',
            'is_admin' => true,
        ]);
        $election = Election::query()->create(['title' => 'Private Directory Election']);

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
            ->patchJson(route('elections.private-voters.selection', ['election' => $election, 'user' => 42]), ['assigned' => true])
            ->assertOk()
            ->assertJsonPath('data.assigned', true)
            ->assertJsonPath('data.totalAssigned', 2);

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

    public function test_private_election_cannot_be_submitted_for_payment_before_its_csv_is_processed(): void
    {
        $organizer = User::factory()->create();
        $service = $this->app->make(ElectionService::class);
        $election = $service->create($organizer, [
            'title' => 'Private Payment Gate Election',
            'position_sought' => 'Class Representative',
            'description' => 'A private election must receive its named voter list before payment.',
            'eligibility_mode' => 'PRIVATE',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Upload a private voter spreadsheet or assign at least one voter');

        $service->transition($organizer, $election->getKey(), 'submit_for_payment', 'password', ['version' => 1]);
    }
}
