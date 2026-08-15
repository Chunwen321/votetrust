<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
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

    public function test_paying_the_election_hosting_fee_shows_the_reference_to_copy_back(): void
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
            ->assertSee('Payment reference')
            ->assertSee($payment->fresh()->provider_reference)
            ->assertSee('Back to my election');
    }
}
