<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Election;
use App\Models\User;
use App\Models\VoterEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_register_with_a_normalised_ic_number(): void
    {
        $response = $this->post(route('register.store'), [
            'full_name' => 'Chew   Member',
            'email' => 'CHEW@example.test',
            'ic_number' => '010203-10-1234',
            'nationality_country_code' => 'MY',
            'phone_number' => '+60 12-345 6789',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ]);

        $response->assertRedirect(route('profile.verify'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('user', [
            'name' => 'Chew Member',
            'full_name' => 'Chew Member',
            'email' => 'chew@example.test',
            'ic_number' => '010203101234',
            'date_of_birth' => '2001-02-03 00:00:00',
            'nationality_country_code' => 'MY',
            'phone_number' => '+60 12-345 6789',
            'account_status' => 'ACTIVE',
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    public function test_a_foreign_member_registers_with_a_passport_and_date_of_birth(): void
    {
        $this->post(route('register.store'), [
            'full_name' => 'Aisha Rahman',
            'email' => 'aisha.foreign@example.test',
            'nationality_country_code' => 'SG',
            'passport_number' => 'e-123 4567',
            'date_of_birth' => '1998-04-12',
            'phone_number' => '+65 8123 4567',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ])->assertRedirect(route('profile.verify'));

        $this->assertDatabaseHas('user', [
            'full_name' => 'Aisha Rahman',
            'email' => 'aisha.foreign@example.test',
            'ic_number' => null,
            'passport_number' => 'E1234567',
            'date_of_birth' => '1998-04-12 00:00:00',
            'nationality_country_code' => 'SG',
            'is_verified' => false,
        ]);
    }

    public function test_an_unverified_malaysian_member_can_update_their_ic_number_before_ekyc(): void
    {
        $user = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Before Verification',
            'email' => 'before.verification@example.test',
            'ic_number' => '010203101234',
            'date_of_birth' => '2001-02-03',
            'nationality_country_code' => 'MY',
            'verification_status' => 'IN_PROGRESS',
            'verification_session_id' => 'didit-session',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'full_name' => 'Before Verification',
                'email' => 'before.verification@example.test',
                'ic_number' => '020304-10-4321',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('user', [
            'id' => $user->getKey(),
            'ic_number' => '020304104321',
            'passport_number' => null,
            'date_of_birth' => '2002-03-04 00:00:00',
            'verification_status' => 'NOT_STARTED',
            'verification_session_id' => null,
        ]);
    }

    public function test_an_unverified_foreign_member_can_update_their_passport_number_before_ekyc(): void
    {
        $user = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Passport Member',
            'email' => 'passport.member@example.test',
            'ic_number' => null,
            'passport_number' => 'E1234567',
            'date_of_birth' => '1998-04-12',
            'nationality_country_code' => 'SG',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'full_name' => 'Passport Member',
                'email' => 'passport.member@example.test',
                'passport_number' => 's-987 6543',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('user', [
            'id' => $user->getKey(),
            'ic_number' => null,
            'passport_number' => 'S9876543',
            'date_of_birth' => '1998-04-12 00:00:00',
        ]);
    }

    public function test_a_verified_member_cannot_change_their_identity_document(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Verified Member',
            'email' => 'verified.member@example.test',
            'ic_number' => '010203101234',
            'nationality_country_code' => 'MY',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'full_name' => 'Verified Member',
                'email' => 'verified.member@example.test',
                'ic_number' => '020304104321',
            ])
            ->assertSessionHasErrors('ic_number');

        $this->assertDatabaseHas('user', [
            'id' => $user->getKey(),
            'ic_number' => '010203101234',
        ]);
    }

    public function test_registration_rejects_an_email_address_that_is_already_registered(): void
    {
        User::factory()->create(['email' => 'existing.member@example.test']);

        $this->from(route('login'))->post(route('register.store'), [
            'full_name' => 'Another Member',
            'email' => 'existing.member@example.test',
            'ic_number' => '020304101234',
            'nationality_country_code' => 'MY',
            'phone_number' => '+60 12-345 6789',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_availability_check_reports_taken_email_and_ic_number_in_their_normalised_forms(): void
    {
        User::factory()->create([
            'email' => 'existing.member@example.test',
            'ic_number' => '010203101234',
        ]);

        $this->postJson(route('register.availability'), [
            'field' => 'email',
            'value' => 'EXISTING.MEMBER@EXAMPLE.TEST',
        ])
            ->assertOk()
            ->assertExactJson([
                'field' => 'email',
                'available' => false,
                'message' => 'The email has already been taken.',
            ]);

        $this->postJson(route('register.availability'), [
            'field' => 'ic_number',
            'value' => '010203-10-1234',
        ])
            ->assertOk()
            ->assertExactJson([
                'field' => 'ic_number',
                'available' => false,
                'message' => 'The IC number has already been taken.',
            ]);
    }

    public function test_registration_page_includes_ic_display_formatting_and_password_requirements(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('data-ic-number', false)
            ->assertSee('data-phone-number', false)
            ->assertSee('data-phone-country-prefix', false)
            ->assertSee('data-registration-availability-url', false)
            ->assertSeeText('Hyphens are added automatically.')
            ->assertSeeText('At least 12 characters')
            ->assertDontSeeText('Passwords match')
            ->assertDontSeeText('Trusted digital elections');
    }

    public function test_registration_rejects_an_implausibly_short_phone_number(): void
    {
        $this->post(route('register.store'), [
            'full_name' => 'Short Phone',
            'email' => 'short.phone@example.test',
            'ic_number' => '010203101234',
            'nationality_country_code' => 'MY',
            'phone_number' => '123123',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ])->assertSessionHasErrors('phone_number');

        $this->assertGuest();
    }

    public function test_repeated_failed_logins_disable_an_active_account(): void
    {
        config()->set('votetrust-user.auth.max_failed_attempts', 2);
        $user = User::factory()->create([
            'email' => 'protected.user@example.test',
            'full_name' => 'Protected User',
            'password' => Hash::make('Correct!Password123'),
            'account_status' => 'ACTIVE',
            'failed_login_attempts' => 0,
        ]);

        $payload = ['email' => 'protected.user@example.test', 'password' => 'Wrong!Password123'];
        $this->post(route('login.store'), $payload)->assertSessionHasErrors('email');
        $this->post(route('login.store'), $payload)->assertSessionHasErrors('email');

        $user->refresh();
        self::assertSame('DISABLED', $user->account_status);
        self::assertSame(2, $user->failed_login_attempts);
        self::assertNotNull($user->account_disabled_at);
        $this->assertGuest();
    }

    public function test_the_internal_api_returns_approved_voter_eligibility(): void
    {
        config()->set('votetrust-user.api.key', 'test-user-api-key');
        config()->set('votetrust-user.services.election.base_url', 'https://election.test');
        config()->set('votetrust-user.services.election.api_key', 'election-service-key');
        $user = User::factory()->create([
            'email' => 'eligible.user@example.test',
            'full_name' => 'Eligible User',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
            'date_of_birth' => '2000-05-10',
            'nationality_country_code' => 'MY',
        ]);
        $election = Election::query()->create([
            'title' => 'Eligibility Test Election',
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);
        Http::fake(function (ClientRequest $request) use ($election): mixed {
            return Http::response([
                'status' => 'S',
                'message' => 'Election availability retrieved.',
                'requestID' => $request['requestID'],
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'electionId' => $election->getKey(),
                    'eligibility' => ['type' => 'PRIVATE'],
                ],
            ]);
        });

        $url = route('api.voters.eligibility').'?'.http_build_query([
            'voterId' => $user->getKey(),
            'electionId' => $election->getKey(),
            'requestID' => 'request_1234',
            'timestamp' => now()->toAtomString(),
        ]);

        $response = $this->withHeader('X-User-Api-Key', 'test-user-api-key')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'request_1234')
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.reasonCode', 'APPROVED')
            ->assertJsonPath('data.identity.dateOfBirth', '2000-05-10')
            ->assertJsonPath('data.identity.nationality.countryCode', 'MY')
            ->assertJsonStructure([
                'status',
                'message',
                'requestID',
                'timestamp',
                'data' => ['voterId', 'electionId', 'eligible', 'reasonCode', 'identity'],
            ]);

        self::assertNotFalse(strtotime((string) $response->json('timestamp')));
    }

    public function test_an_unverified_profile_is_not_eligible_to_vote_through_the_user_api(): void
    {
        config()->set('votetrust-user.api.key', 'test-user-api-key');
        $user = User::factory()->unverifiedProfile()->create([
            'email' => 'unverified.voter@example.test',
            'ic_number' => '000101101234',
            'date_of_birth' => '2000-01-01',
        ]);
        $election = Election::query()->create([
            'title' => 'Verification Required Election',
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);

        $this->withHeader('X-User-Api-Key', 'test-user-api-key')
            ->getJson(route('api.voters.eligibility').'?'.http_build_query([
                'voterId' => $user->getKey(),
                'electionId' => $election->getKey(),
                'requestID' => 'request_unverified_voter',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.reasonCode', 'PROFILE_UNVERIFIED');
    }

    public function test_an_unverified_profile_is_redirected_from_election_voting_and_candidate_actions(): void
    {
        $user = User::factory()->unverifiedProfile()->create([
            'ic_number' => '000101101234',
            'date_of_birth' => '2000-01-01',
        ]);

        $this->actingAs($user)
            ->get(route('elections.create'))
            ->assertRedirect(route('profile.verify'));
        $this->actingAs($user)
            ->get(route('voting.index'))
            ->assertRedirect(route('profile.verify'));
        $this->actingAs($user)
            ->get(route('candidate.applications.index'))
            ->assertRedirect(route('profile.verify'));
    }

    public function test_an_administrator_is_exempt_from_ekyc_and_can_open_the_administrative_workspace(): void
    {
        $admin = User::factory()->unverifiedProfile()->create([
            'is_admin' => true,
            'ic_number' => '000101101234',
            'date_of_birth' => '2000-01-01',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('profile.verify.store'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'System administrator accounts are exempt from eKYC verification.');

        $admin->refresh();
        self::assertFalse($admin->isVerified());
        self::assertSame('NOT_STARTED', $admin->verification_status);
    }

    public function test_a_member_can_complete_local_ekyc_profile_verification_after_consent(): void
    {
        $user = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Verified Member',
            'ic_number' => '000101101234',
            'date_of_birth' => '2000-01-01',
            'nationality_country_code' => 'MY',
        ]);

        $this->actingAs($user)
            ->post(route('profile.verify.store'), ['ekyc_consent' => '1'])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('user', [
            'id' => $user->getKey(),
            'is_verified' => true,
        ]);
        self::assertTrue((bool) $user->refresh()->is_verified);
        self::assertNotNull($user->verified_at);
        self::assertStringStartsWith('LOCAL-MY_IC-', (string) $user->verification_reference);
    }

    public function test_a_foreign_member_can_complete_local_ekyc_with_their_passport(): void
    {
        $user = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Passport Member',
            'nationality_country_code' => 'SG',
            'ic_number' => null,
            'passport_number' => 'E1234567',
            'date_of_birth' => '1998-04-12',
        ]);

        $this->actingAs($user)
            ->post(route('profile.verify.store'), ['ekyc_consent' => '1'])
            ->assertRedirect(route('profile.show'));

        $user->refresh();
        self::assertTrue($user->isVerified());
        self::assertStringStartsWith('LOCAL-PASSPORT-', (string) $user->verification_reference);
    }

    public function test_a_member_is_redirected_to_didit_after_a_server_side_hosted_session_is_created(): void
    {
        $this->configureDidit();
        $sessionId = '0e4c76c6-b987-44f0-9b94-482f9060d450';
        Http::fake([
            'https://verification.didit.me/v3/session/' => Http::response([
                'session_id' => $sessionId,
                'url' => 'https://verify.didit.me/session/secure-session-token',
                'status' => 'Not Started',
            ], 201),
        ]);
        $user = User::factory()->unverifiedProfile()->create([
            'full_name' => 'Didit Member',
            'ic_number' => '000101101234',
            'date_of_birth' => '2000-01-01',
            'nationality_country_code' => 'MY',
        ]);

        $this->actingAs($user)
            ->post(route('profile.verify.store'), ['ekyc_consent' => '1'])
            ->assertRedirect('https://verify.didit.me/session/secure-session-token');

        $this->assertDatabaseHas('user', [
            'id' => $user->getKey(),
            'is_verified' => false,
            'verification_provider' => 'DIDIT',
            'verification_session_id' => $sessionId,
            'verification_status' => 'PENDING',
        ]);
        Http::assertSent(function (ClientRequest $request) use ($user): bool {
            $payload = $this->requestInput($request);

            return $request->method() === 'POST'
                && $request->url() === 'https://verification.didit.me/v3/session/'
                && $request->hasHeader('x-api-key', 'test-didit-api-key')
                && ($payload['workflow_id'] ?? null) === 'd89ffaf6-286d-4a9f-a3d7-bc7d15d6a12a'
                && ($payload['vendor_data'] ?? null) === 'votetrust-user:'.$user->getKey()
                && ($payload['expected_details']['id_country'] ?? null) === 'MYS'
                && ($payload['expected_details']['expected_document_types'] ?? null) === ['ID'];
        });
    }

    public function test_only_a_valid_didit_webhook_for_the_matching_session_can_verify_a_user(): void
    {
        $this->configureDidit();
        $sessionId = '6e199f42-a030-441c-a7f4-85df36592954';
        $user = User::factory()->unverifiedProfile()->create([
            'verification_provider' => 'DIDIT',
            'verification_session_id' => $sessionId,
            'verification_status' => 'PENDING',
        ]);
        $payload = [
            'event_id' => '56b1302d-c91c-46fb-82c8-829405ed7976',
            'webhook_type' => 'status.updated',
            'timestamp' => time(),
            'created_at' => time(),
            'application_id' => 'bec3353d-881a-44d4-bfc8-29d5a719b270',
            'environment' => 'sandbox',
            'session_id' => $sessionId,
            'workflow_id' => 'd89ffaf6-286d-4a9f-a3d7-bc7d15d6a12a',
            'vendor_data' => 'votetrust-user:'.$user->getKey(),
            'status' => 'Approved',
            'decision' => ['id_verifications' => [['status' => 'Approved']]],
        ];
        $timestamp = (string) time();

        $this->postJson(route('api.webhooks.didit'), $payload, [
            'X-Timestamp' => $timestamp,
            'X-Signature-V2' => $this->diditSignature($payload),
        ])->assertOk()->assertJsonPath('ok', true);

        $user->refresh();
        self::assertTrue($user->isVerified());
        self::assertSame('APPROVED', $user->verification_status);
        self::assertSame($sessionId, $user->verification_reference);
        self::assertSame($payload['event_id'], $user->verification_last_event_id);
    }

    public function test_an_invalid_didit_webhook_never_changes_the_verification_state(): void
    {
        $this->configureDidit();
        $user = User::factory()->unverifiedProfile()->create([
            'verification_provider' => 'DIDIT',
            'verification_session_id' => '74dc9dab-2065-4223-8486-155e7a1d61eb',
            'verification_status' => 'PENDING',
        ]);
        $payload = [
            'event_id' => 'b6cc58f7-96e6-47cf-acbe-fabc651c373f',
            'webhook_type' => 'status.updated',
            'timestamp' => time(),
            'session_id' => '74dc9dab-2065-4223-8486-155e7a1d61eb',
            'workflow_id' => 'd89ffaf6-286d-4a9f-a3d7-bc7d15d6a12a',
            'vendor_data' => 'votetrust-user:'.$user->getKey(),
            'status' => 'Approved',
        ];

        $this->postJson(route('api.webhooks.didit'), $payload, [
            'X-Timestamp' => (string) time(),
            'X-Signature-V2' => 'not-a-valid-signature',
        ])->assertUnauthorized();

        $user->refresh();
        self::assertFalse($user->isVerified());
        self::assertSame('PENDING', $user->verification_status);
    }

    public function test_the_internal_api_rejects_an_invalid_request_with_an_ifa_envelope(): void
    {
        config()->set('votetrust-user.api.key', 'test-user-api-key');

        $response = $this->withHeader('X-User-Api-Key', 'test-user-api-key')
            ->getJson(route('api.voters.eligibility').'?'.http_build_query([
                'voterId' => 1,
                'electionId' => 1,
                'requestID' => 'request_5678',
                'timestamp' => 'not-a-timestamp',
            ]));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('status', 'E')
            ->assertJsonPath('requestID', 'request_5678')
            ->assertJsonStructure(['status', 'message', 'requestID', 'timestamp', 'errors']);

        self::assertNotFalse(strtotime((string) $response->json('timestamp')));
    }

    public function test_an_administrator_can_manage_election_participants(): void
    {
        $admin = User::factory()->create([
            'email' => 'system.admin@example.test',
            'full_name' => 'System Admin',
            'is_admin' => true,
            'account_status' => 'ACTIVE',
        ]);
        $member = User::factory()->create([
            'email' => 'election.member@example.test',
            'full_name' => 'Election Member',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $election = Election::query()->create([
            'title' => 'Participant Test Election',
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);
        $this->configureElectionAndCandidateServices();
        Http::preventStrayRequests();
        Http::fake(fn (ClientRequest $request) => $this->crossModuleResponse($request, $election));

        $this->actingAs($admin)->post(route('admin.participants.voters.store', $election->getKey()), [
            'user_id' => $member->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ])->assertRedirect(route('admin.participants.show', $election->getKey()).'#voters');

        $this->actingAs($admin)->post(route('admin.participants.candidates.store', $election->getKey()), [
            'user_id' => $member->getKey(),
            'status' => 'Approved',
        ])->assertRedirect(route('admin.participants.show', $election->getKey()).'#candidates');

        $this->assertDatabaseHas('voter', [
            'user_id' => $member->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
            'added_by' => $admin->getKey(),
        ]);
        $this->assertDatabaseMissing('candidacy', [
            'user_id' => $member->getKey(),
            'election_id' => $election->getKey(),
        ]);
        Http::assertSent(function (ClientRequest $request) use ($election, $member): bool {
            $payload = $this->requestInput($request);

            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://candidate.test/api/v1/elections/'.$election->getKey().'/candidates')
                && $request->hasHeader('X-Internal-Api-Key', 'candidate-consumer-key')
                && ($payload['electionId'] ?? null) === $election->getKey()
                && ($payload['userId'] ?? null) === $member->getKey()
                && ($payload['status'] ?? null) === 'Approved'
                && preg_match('/\Ausr_[a-f0-9]{32}\z/', (string) ($payload['requestID'] ?? '')) === 1;
        });

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Election participant control');
        $this->actingAs($admin)
            ->get(route('admin.participants.show', $election->getKey()))
            ->assertOk()
            ->assertSee('Election Member');
    }

    public function test_an_administrator_can_filter_and_change_member_account_access(): void
    {
        $admin = User::factory()->create([
            'email' => 'access.admin@example.test',
            'full_name' => 'Access Admin',
            'is_admin' => true,
            'account_status' => 'ACTIVE',
        ]);
        $member = User::factory()->create([
            'email' => 'member.access@example.test',
            'full_name' => 'Member Access',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'member.access@example.test', 'status' => 'ACTIVE']))
            ->assertOk()
            ->assertSee('Member Access')
            ->assertSee('Member account management');

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $member), ['status' => 'DISABLED'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('user', [
            'id' => $member->getKey(),
            'account_status' => 'DISABLED',
            'failed_login_attempts' => 0,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $member), ['status' => 'ACTIVE'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('user', [
            'id' => $member->getKey(),
            'account_status' => 'ACTIVE',
            'account_disabled_at' => null,
        ]);
    }

    public function test_a_disabled_member_is_signed_out_before_entering_protected_pages(): void
    {
        $member = User::factory()->create([
            'email' => 'disabled.member@example.test',
            'is_admin' => false,
            'account_status' => 'DISABLED',
        ]);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_a_member_can_open_dashboard_profile_and_voter_enrolments(): void
    {
        $user = User::factory()->create([
            'email' => 'member.pages@example.test',
            'full_name' => 'Member Pages',
            'ic_number' => '020304101234',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $election = Election::query()->create([
            'title' => 'Member Pages Election',
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $user->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::PENDING,
        ]);
        $this->configureElectionService();
        Http::preventStrayRequests();
        Http::fake(fn (ClientRequest $request) => $this->crossModuleResponse($request, $election));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('How VoteTrust works')
            ->assertSee('Your election space');
        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('020304101234');
        $this->actingAs($user)->get(route('voter.dashboard'))
            ->assertOk()
            ->assertSee('Member Pages Election')
            ->assertSee('PENDING');

        Http::assertSent(function (ClientRequest $request) use ($election): bool {
            $query = $this->requestInput($request);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://election.test/api/v1/elections/summaries?')
                && $request->hasHeader('X-Internal-Api-Key', 'election-consumer-key')
                && in_array((string) $election->getKey(), (array) ($query['electionIds'] ?? []), true);
        });
    }

    private function configureElectionService(): void
    {
        config()->set('votetrust-user.services.election', [
            'base_url' => 'https://election.test',
            'api_key' => 'election-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);
    }

    private function configureDidit(): void
    {
        config()->set('votetrust-user.ekyc.driver', 'didit');
        config()->set('votetrust-user.ekyc.didit', [
            'base_url' => 'https://verification.didit.me',
            'api_key' => 'test-didit-api-key',
            'workflow_id' => 'd89ffaf6-286d-4a9f-a3d7-bc7d15d6a12a',
            'webhook_secret' => 'test-didit-webhook-secret',
            'webhook_tolerance_seconds' => 300,
        ]);
    }

    /** @param array<array-key, mixed> $payload */
    private function diditSignature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($this->sortDiditPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'test-didit-webhook-secret');
    }

    private function sortDiditPayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_float($value) && floor($value) === $value ? (int) $value : $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortDiditPayload($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortDiditPayload($item);
        }

        return $value;
    }

    private function configureElectionAndCandidateServices(): void
    {
        $this->configureElectionService();
        config()->set('votetrust-user.services.candidate', [
            'base_url' => 'https://candidate.test',
            'api_key' => 'candidate-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);
    }

    private function crossModuleResponse(ClientRequest $request, Election $election): mixed
    {
        $input = $this->requestInput($request);
        $requestId = (string) ($input['requestID'] ?? 'UNKNOWN');
        $electionSummary = [
            'electionId' => $election->getKey(),
            'title' => $election->title,
            'lifecycleState' => $election->status,
            'organizerUserId' => $election->organizer_id,
            'updatedAt' => $election->updated_at?->toAtomString(),
        ];

        if (str_starts_with($request->url(), 'https://election.test/')) {
            if (str_contains($request->url(), '/admin/overview')) {
                $data = [
                    'counts' => ['elections' => 1, 'active' => 0, 'pendingApprovals' => 0],
                    'recentElections' => [$electionSummary],
                    'pendingApprovals' => [],
                ];
            } elseif (str_contains($request->url(), '/organizer/')) {
                $data = ['elections' => []];
            } else {
                $data = ['elections' => [$electionSummary]];
            }

            return Http::response([
                'status' => 'S',
                'message' => 'Election data retrieved.',
                'requestID' => $requestId,
                'timestamp' => now()->toAtomString(),
                'data' => $data,
            ]);
        }

        if (str_starts_with($request->url(), 'https://candidate.test/')) {
            if (str_contains($request->url(), '/metrics')) {
                $data = ['candidates' => 0, 'pending' => 0];
            } elseif ($request->method() === 'GET') {
                $data = ['electionId' => $election->getKey(), 'candidates' => []];
            } else {
                $data = [
                    'electionId' => $input['electionId'] ?? null,
                    'userId' => $input['userId'] ?? null,
                    'status' => $input['status'] ?? null,
                ];
            }

            return Http::response([
                'status' => 'S',
                'message' => 'Candidate data retrieved.',
                'requestID' => $requestId,
                'timestamp' => now()->toAtomString(),
                'data' => $data,
            ]);
        }

        return Http::response([], 404);
    }

    /** @return array<string, mixed> */
    private function requestInput(ClientRequest $request): array
    {
        if ($request->method() === 'GET') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query;
        }

        $payload = json_decode($request->body(), true);

        return is_array($payload) ? $payload : [];
    }
}
