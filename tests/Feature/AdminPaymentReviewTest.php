<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AdminPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_highlight_follows_the_current_workspace(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.dashboard').'" class="is-active">Dashboard</a>', false);

        $this->actingAs($admin)
            ->get(route('admin.elections.index'))
            ->assertOk()
            ->assertSee('site-nav-dropdown is-active', false)
            ->assertSee('Election oversight');

        Http::fake(['*/api/v1/payments/ping' => Http::response([], 500)]);
        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Payment review')
            ->assertSee('site-nav-dropdown is-active', false);
    }

    public function test_admin_payment_queue_lists_and_filters_submitted_election_payments(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organizer = User::factory()->create(['full_name' => 'Queue Organizer']);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $awaiting = $this->paymentElection($organizer, 'Awaiting Payment Review', null);
        $verified = $this->paymentElection($organizer, 'Verified Payment Review', 'VT-HOST-VERIFIED-01', true);
        $this->paymentElection($organizer, 'Not In Payment Queue', null, false, Election::PENDING_PAYMENT);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($awaiting->title)
            ->assertSee($verified->title)
            ->assertDontSee('Not In Payment Queue')
            ->assertSee('2 matching payments', false);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['verification' => 'awaiting']))
            ->assertOk()
            ->assertSee($awaiting->title)
            ->assertDontSee($verified->title);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['search' => 'VERIFIED-01']))
            ->assertOk()
            ->assertSee($verified->title)
            ->assertDontSee($awaiting->title);
    }

    public function test_payment_must_be_verified_before_admin_can_approve_the_election(): void
    {
        config()->set('election.payment.url', 'https://payments.example.test/api/v1/payments/verify');
        config()->set('election.payment.api_key', 'payment-contract-secret');

        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => Hash::make('Admin!Pass1'),
        ]);
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = $this->paymentElection($organizer, 'Separate Payment Approval', null);

        $this->actingAs($admin)
            ->from(route('admin.elections.show', $election))
            ->post(route('admin.elections.transition', $election), [
                'action' => 'approve',
                'current_password' => 'Admin!Pass1',
                'version' => 1,
            ])
            ->assertRedirect(route('admin.elections.show', $election))
            ->assertSessionHas('error', 'Verify the hosting payment before approving this election.');
        self::assertNull($election->refresh()->approved_at);

        Http::fake(static function (Request $request) {
            return Http::response([
                'status' => 'S',
                'message' => 'Hosting payment confirmed.',
                'requestID' => $request['requestID'],
                'timestamp' => $request['timestamp'],
                'data' => [
                    'paymentReference' => 'VT-HOST-PAID-009',
                    'paymentStatus' => 'CONFIRMED',
                    'amount' => $request['expectedAmount'],
                    'currency' => $request['currency'],
                    'payerId' => $request['payerId'],
                    'electionId' => $request['electionId'],
                    'purpose' => 'ELECTION_HOSTING',
                    'paymentId' => 909,
                ],
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.payments.verify', $election), [
                'current_password' => 'Admin!Pass1',
                'version' => 1,
            ])
            ->assertRedirect(route('admin.payments.index'));

        $election->refresh();
        self::assertSame(Election::PENDING_ADMIN_APPROVAL, $election->status);
        self::assertNotNull($election->payment_verified_at);
        self::assertSame('VT-HOST-PAID-009', $election->payment_reference);
        self::assertNull($election->approved_at);
        self::assertSame(2, $election->version);

        $this->actingAs($admin)
            ->post(route('admin.elections.transition', $election), [
                'action' => 'approve',
                'current_password' => 'Admin!Pass1',
                'version' => 2,
            ])
            ->assertRedirect(route('admin.elections.show', $election));

        $election->refresh();
        self::assertSame(Election::NOMINATION_OPEN, $election->status);
        self::assertNotNull($election->approved_at);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => ! isset($request['paymentReference']));
    }

    public function test_admin_approval_expires_a_public_election_when_nominations_already_ended(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => Hash::make('Admin!Pass1'),
        ]);
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = $this->paymentElection($organizer, 'Expired Administrator Review', 'VT-HOST-PAID-LATE', true);
        $election->forceFill([
            'nomination_start' => now()->subDays(2),
            'nomination_end' => now()->subDay(),
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.elections.transition', $election), [
                'action' => 'approve',
                'current_password' => 'Admin!Pass1',
                'version' => 1,
            ])
            ->assertRedirect(route('admin.elections.show', $election))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'expired'));

        $election->refresh();
        self::assertSame(Election::EXPIRED, $election->status);
        self::assertNull($election->approved_at);
        self::assertSame('expire_schedule', collect($election->transition_history_json)->last()['action']);
    }

    public function test_admin_does_not_call_payment_api_for_an_expired_public_election(): void
    {
        config()->set('election.payment.url', 'https://payments.example.test/api/v1/payments/verify');
        config()->set('election.payment.api_key', 'payment-contract-secret');
        Http::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => Hash::make('Admin!Pass1'),
        ]);
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = $this->paymentElection($organizer, 'Expired Payment Verification', null);
        $election->forceFill([
            'nomination_start' => now()->subDays(2),
            'nomination_end' => now()->subDay(),
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.payments.verify', $election), [
                'current_password' => 'Admin!Pass1',
                'version' => 1,
            ])
            ->assertRedirect(route('admin.elections.show', $election))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'expired'));

        self::assertSame(Election::EXPIRED, $election->refresh()->status);
        self::assertNull($election->payment_verified_at);
        Http::assertNothingSent();
    }

    private function paymentElection(
        User $organizer,
        string $title,
        ?string $reference,
        bool $verified = false,
        string $status = Election::PENDING_ADMIN_APPROVAL,
    ): Election {
        return Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => $title,
            'position_sought' => 'President',
            'description' => 'Election hosting payment review fixture.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'status' => $status,
            'payment_reference' => $reference,
            'payment_verified_at' => $verified ? now() : null,
            'payment_provider_status' => $verified ? 'CONFIRMED' : 'SUBMITTED',
            'transition_history_json' => [],
            'version' => 1,
        ]);
    }
}
