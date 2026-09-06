<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\User;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PIC: Risky Puah Kaho - Payment Module
 * Verifies the fake IDontPay99 checkout page: access control and the
 * magic-card-number outcomes, with no card data ever persisted.
 */
final class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owning_candidate_can_view_the_checkout_page(): void
    {
        $candidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->actingAs($candidate)
            ->get(route('payments.checkout.show', $payment))
            ->assertOk()
            ->assertSee('IDontPay99')
            ->assertSee('Pay MYR 10.00');
    }

    public function test_a_non_owning_user_cannot_view_the_checkout_page(): void
    {
        $candidate = User::factory()->create();
        $otherUser = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->actingAs($otherUser)
            ->get(route('payments.checkout.show', $payment))
            ->assertForbidden();
    }

    public function test_paying_with_a_normal_card_confirms_the_payment(): void
    {
        $candidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->actingAs($candidate)
            ->post(route('payments.checkout.charge', $payment), [
                'card_number' => '4242 4242 4242 4242',
                'card_holder' => 'A. Candidate',
                'expiry' => '12/29',
                'cvv' => '123',
            ])
            ->assertRedirect(route('payments.checkout.show', $payment));

        self::assertSame('Paid', $payment->fresh()->status);
    }

    public function test_the_magic_decline_card_fails_the_payment(): void
    {
        $candidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->actingAs($candidate)
            ->post(route('payments.checkout.charge', $payment), [
                'card_number' => '4000 0000 0000 0002',
                'card_holder' => 'A. Candidate',
                'expiry' => '12/29',
                'cvv' => '123',
            ])
            ->assertRedirect(route('payments.checkout.show', $payment));

        self::assertSame('Failed', $payment->fresh()->status);
    }

    public function test_an_expired_card_fails_the_payment(): void
    {
        $candidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->actingAs($candidate)
            ->post(route('payments.checkout.charge', $payment), [
                'card_number' => '4242 4242 4242 4242',
                'card_holder' => 'A. Candidate',
                'expiry' => '01/20',
                'cvv' => '123',
            ])
            ->assertRedirect(route('payments.checkout.show', $payment));

        self::assertSame('Failed', $payment->fresh()->status);
    }

    public function test_no_card_data_is_persisted_anywhere_on_the_payment(): void
    {
        $candidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->actingAs($candidate)->post(route('payments.checkout.charge', $payment), [
            'card_number' => '4242 4242 4242 4242',
            'card_holder' => 'A. Candidate',
            'expiry' => '12/29',
            'cvv' => '123',
        ]);

        $stored = json_encode($payment->fresh()->toArray());
        self::assertStringNotContainsString('4242', (string) $stored);
        self::assertStringNotContainsString('A. Candidate', (string) $stored);
        self::assertStringNotContainsString('123', (string) $stored);
    }

    public function test_an_already_settled_payment_cannot_be_paid_again(): void
    {
        $candidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Paid',
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
            'verified_at' => now(),
        ]);

        $this->actingAs($candidate)
            ->post(route('payments.checkout.charge', $payment), [
                'card_number' => '4242 4242 4242 4242',
                'card_holder' => 'A. Candidate',
                'expiry' => '12/29',
                'cvv' => '123',
            ])
            ->assertRedirect(route('payments.checkout.show', $payment));

        self::assertSame('Paid', $payment->fresh()->status);
    }

    public function test_the_organizer_can_reach_election_checkout_and_it_creates_a_hosting_payment(): void
    {
        $organizer = User::factory()->create();
        $organizerRecord = Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizerRecord->getKey(),
            'title' => 'Checkout Bridge Election',
            'position_sought' => 'President',
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $response = $this->actingAs($organizer)
            ->get(route('payments.checkout.election', $election));

        $payment = Payment::query()->where('election_id', $election->getKey())->firstOrFail();
        $response->assertRedirect(route('payments.checkout.show', $payment));
        self::assertSame(Payment::PURPOSE_ELECTION_HOSTING, $payment->purpose);
        self::assertSame('Pending', $payment->status);
        self::assertSame((int) $organizer->getKey(), (int) $payment->organizer_id);
        self::assertNotNull($payment->provider_reference);
    }

    public function test_overdue_public_election_expires_before_checkout_creates_a_payment(): void
    {
        Http::fake();
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Expired Checkout Election',
            'position_sought' => 'President',
            'description' => 'A public election that must expire before checkout begins.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => now()->subDays(2),
            'nomination_end' => now()->subDay(),
            'voting_start' => now()->addDay(),
            'voting_end' => now()->addDays(2),
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'transition_history_json' => [],
            'version' => 1,
        ]);

        $this->actingAs($organizer)
            ->get(route('payments.checkout.election', $election))
            ->assertRedirect(route('elections.show', $election))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'expired'));

        self::assertSame(Election::EXPIRED, $election->refresh()->status);
        self::assertSame(0, Payment::query()->where('election_id', $election->getKey())->count());

        $this->actingAs($organizer)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSee('Schedule expired')
            ->assertSee('Organise a new election');
        Http::assertNothingSent();
    }

    public function test_overdue_private_election_remains_eligible_for_checkout(): void
    {
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Private Checkout Election',
            'position_sought' => 'Class Representative',
            'description' => 'A private election remains outside the public schedule-expiry rule.',
            'eligibility_rules' => EligibilityRules::privateElection()->toStorage(),
            'nomination_start' => now()->subDays(2),
            'nomination_end' => now()->subDay(),
            'voting_start' => now()->addDay(),
            'voting_end' => now()->addDays(2),
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '120.00',
            'currency' => 'MYR',
            'transition_history_json' => [],
            'version' => 1,
        ]);

        $response = $this->actingAs($organizer)
            ->get(route('payments.checkout.election', $election));

        $payment = Payment::query()->where('election_id', $election->getKey())->firstOrFail();
        $response->assertRedirect(route('payments.checkout.show', $payment));
        self::assertSame(Election::PENDING_PAYMENT, $election->refresh()->status);
    }

    public function test_stale_hosting_checkout_cannot_charge_after_public_nomination_ends(): void
    {
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Stale Hosting Checkout',
            'position_sought' => 'President',
            'description' => 'A checkout opened before its public election schedule expired.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => now()->subDays(2),
            'nomination_end' => now()->subDay(),
            'voting_start' => now()->addDay(),
            'voting_end' => now()->addDays(2),
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'transition_history_json' => [],
            'version' => 1,
        ]);
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'organizer_id' => $organizer->getKey(),
            'election_id' => $election->getKey(),
            'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
            'provider_reference' => 'VT-HOST-STALE-CHECKOUT',
        ]);

        $this->actingAs($organizer)
            ->post(route('payments.checkout.charge', $payment), [
                'card_number' => '4242 4242 4242 4242',
                'card_holder' => 'Org Anizer',
                'expiry' => '12/29',
                'cvv' => '123',
            ])
            ->assertRedirect(route('elections.show', $election));

        self::assertSame(Election::EXPIRED, $election->refresh()->status);
        self::assertSame('Pending', $payment->refresh()->status);
        self::assertNull($payment->verified_at);
    }

    public function test_pending_payment_page_disables_admin_review_until_payment_is_confirmed(): void
    {
        config()->set('election.payment.url', 'https://payments.example.test/api/v1/payments/verify');
        config()->set('election.payment.api_key', 'payment-contract-secret');
        Http::fake(static function (Request $request) {
            return Http::response([
                'status' => 'F',
                'message' => 'Payment is pending.',
                'requestID' => $request['requestID'],
                'timestamp' => $request['timestamp'],
                'data' => [
                    'paymentReference' => 'VT-HOST-PENDING-001',
                    'paymentStatus' => 'PENDING',
                    'amount' => $request['expectedAmount'],
                    'currency' => $request['currency'],
                    'payerId' => $request['payerId'],
                    'electionId' => $request['electionId'],
                    'purpose' => 'ELECTION_HOSTING',
                    'paymentId' => 991,
                ],
            ]);
        });

        $organizer = User::factory()->create();
        $organizerRecord = Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizerRecord->getKey(),
            'title' => 'Reference Free Checkout Election',
            'position_sought' => 'President',
            'description' => 'An election whose hosting payment is matched automatically.',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'version' => 1,
        ]);

        $response = $this->actingAs($organizer)
            ->get(route('elections.show', $election));

        $response
            ->assertOk()
            ->assertSee('Pay MYR 100.00 hosting fee')
            ->assertSee('Send for administrator review')
            ->assertSee('election-button-payment', false)
            ->assertSee('Awaiting payment')
            ->assertDontSee('name="payment_reference"', false);
        self::assertSame(1, preg_match(
            '/<form[^>]*>.*?name="action" value="submit_payment".*?<\/form>/s',
            (string) $response->getContent(),
            $paymentForm,
        ));
        self::assertStringNotContainsString('name="current_password"', $paymentForm[0]);
        self::assertSame(1, preg_match(
            '/<form[^>]*>.*?name="action" value="cancel".*?<\/form>/s',
            (string) $response->getContent(),
            $cancellationForm,
        ));
        self::assertStringContainsString('name="current_password"', $cancellationForm[0]);
        self::assertMatchesRegularExpression(
            '/<button[^>]*type="submit"[^>]*disabled[^>]*>Send for administrator review<\/button>/',
            (string) $response->getContent(),
        );
    }

    public function test_confirmed_payment_enables_admin_review_submission(): void
    {
        config()->set('election.payment.url', 'https://payments.example.test/api/v1/payments/verify');
        config()->set('election.payment.api_key', 'payment-contract-secret');
        Http::fake(static function (Request $request) {
            return Http::response([
                'status' => 'S',
                'message' => 'Hosting payment confirmed.',
                'requestID' => $request['requestID'],
                'timestamp' => $request['timestamp'],
                'data' => [
                    'paymentReference' => 'VT-HOST-PAID-001',
                    'paymentStatus' => 'CONFIRMED',
                    'amount' => $request['expectedAmount'],
                    'currency' => $request['currency'],
                    'payerId' => $request['payerId'],
                    'electionId' => $request['electionId'],
                    'purpose' => 'ELECTION_HOSTING',
                    'paymentId' => 992,
                ],
            ]);
        });

        $organizer = User::factory()->create();
        $organizerRecord = Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizerRecord->getKey(),
            'title' => 'Confirmed Hosting Payment Election',
            'position_sought' => 'President',
            'description' => 'An election with a confirmed hosting payment.',
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'version' => 1,
        ]);

        $response = $this->actingAs($organizer)
            ->get(route('elections.show', $election));

        $response
            ->assertOk()
            ->assertSee('Payment confirmed')
            ->assertSee('View confirmed payment');
        self::assertMatchesRegularExpression(
            '/<button[^>]*type="submit"(?![^>]*disabled)[^>]*>Send for administrator review<\/button>/',
            (string) $response->getContent(),
        );
    }

    public function test_election_checkout_reuses_the_existing_payment_instead_of_duplicating(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Checkout Bridge Election',
            'position_sought' => 'President',
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $this->actingAs($organizer)->get(route('payments.checkout.election', $election));
        $this->actingAs($organizer)->get(route('payments.checkout.election', $election));

        self::assertSame(1, Payment::query()->where('election_id', $election->getKey())->count());
    }

    public function test_a_non_organizer_cannot_reach_election_checkout(): void
    {
        $organizer = User::factory()->create();
        $stranger = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Checkout Bridge Election',
            'position_sought' => 'President',
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $this->actingAs($stranger)
            ->get(route('payments.checkout.election', $election))
            ->assertForbidden();
    }

    public function test_election_checkout_is_unavailable_outside_pending_payment(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Checkout Bridge Election',
            'position_sought' => 'President',
            'status' => Election::DRAFT,
        ]);

        $this->actingAs($organizer)
            ->get(route('payments.checkout.election', $election))
            ->assertForbidden();
    }

    public function test_paying_the_election_hosting_fee_returns_to_the_linked_election_without_copying_a_reference(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Checkout Bridge Election',
            'position_sought' => 'President',
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $this->actingAs($organizer)->get(route('payments.checkout.election', $election));
        $payment = Payment::query()->where('election_id', $election->getKey())->firstOrFail();

        $this->actingAs($organizer)->post(route('payments.checkout.charge', $payment), [
            'card_number' => '4242 4242 4242 4242',
            'card_holder' => 'Org Anizer',
            'expiry' => '12/29',
            'cvv' => '123',
        ]);

        $this->actingAs($organizer)
            ->get(route('payments.checkout.show', $payment))
            ->assertOk()
            ->assertSee('Hosting fee paid')
            ->assertSee('Payment linked to Election #'.$election->getKey())
            ->assertSee('No payment reference is required')
            ->assertDontSee($payment->fresh()->provider_reference)
            ->assertSee('Back to my election');
    }
}
