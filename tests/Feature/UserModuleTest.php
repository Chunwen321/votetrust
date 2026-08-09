<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Election;
use App\Models\User;
use App\Models\VoterEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_register_with_a_normalised_ic_number(): void
    {
        $response = $this->post(route('register.store'), [
            'username' => 'chew.member',
            'full_name' => 'Chew   Member',
            'email' => 'CHEW@example.test',
            'ic_number' => '010203-10-1234',
            'password' => 'Strong!Password123',
            'password_confirmation' => 'Strong!Password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('user', [
            'username' => 'chew.member',
            'name' => 'Chew Member',
            'full_name' => 'Chew Member',
            'email' => 'chew@example.test',
            'ic_number' => '010203101234',
            'date_of_birth' => '2001-02-03 00:00:00',
            'nationality_country_code' => 'MY',
            'account_status' => 'ACTIVE',
        ]);
    }

    public function test_repeated_failed_logins_disable_an_active_account(): void
    {
        config()->set('votetrust-user.auth.max_failed_attempts', 2);
        $user = User::factory()->create([
            'username' => 'protected.user',
            'full_name' => 'Protected User',
            'password' => Hash::make('Correct!Password123'),
            'account_status' => 'ACTIVE',
            'failed_login_attempts' => 0,
        ]);

        $payload = ['username' => 'protected.user', 'password' => 'Wrong!Password123'];
        $this->post(route('login.store'), $payload)->assertSessionHasErrors('username');
        $this->post(route('login.store'), $payload)->assertSessionHasErrors('username');

        $user->refresh();
        self::assertSame('DISABLED', $user->account_status);
        self::assertSame(2, $user->failed_login_attempts);
        self::assertNotNull($user->account_disabled_at);
        $this->assertGuest();
    }

    public function test_the_internal_api_returns_approved_voter_eligibility(): void
    {
        config()->set('votetrust-user.api.key', 'test-user-api-key');
        $user = User::factory()->create([
            'username' => 'eligible.user',
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
            'username' => 'system.admin',
            'full_name' => 'System Admin',
            'is_admin' => true,
            'account_status' => 'ACTIVE',
        ]);
        $member = User::factory()->create([
            'username' => 'election.member',
            'full_name' => 'Election Member',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $election = Election::query()->create([
            'title' => 'Participant Test Election',
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);

        $this->actingAs($admin)->post(route('admin.participants.voters.store', $election), [
            'user_id' => $member->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ])->assertRedirect(route('admin.participants.show', $election).'#voters');

        $this->actingAs($admin)->post(route('admin.participants.candidates.store', $election), [
            'user_id' => $member->getKey(),
            'status' => 'Approved',
        ])->assertRedirect(route('admin.participants.show', $election).'#candidates');

        $this->assertDatabaseHas('voter', [
            'user_id' => $member->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
            'added_by' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('candidacy', [
            'user_id' => $member->getKey(),
            'election_id' => $election->getKey(),
            'status' => 'Approved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Election participant control');
        $this->actingAs($admin)
            ->get(route('admin.participants.show', $election))
            ->assertOk()
            ->assertSee('Election Member');
    }

    public function test_an_administrator_can_filter_and_change_member_account_access(): void
    {
        $admin = User::factory()->create([
            'username' => 'access.admin',
            'full_name' => 'Access Admin',
            'is_admin' => true,
            'account_status' => 'ACTIVE',
        ]);
        $member = User::factory()->create([
            'username' => 'member.access',
            'full_name' => 'Member Access',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'member.access', 'status' => 'ACTIVE']))
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
            'username' => 'disabled.member',
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
            'username' => 'member.pages',
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

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Member Pages Election');
        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('020304101234');
        $this->actingAs($user)->get(route('voter.dashboard'))
            ->assertOk()
            ->assertSee('Member Pages Election')
            ->assertSee('PENDING');
    }
}
