<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PIC: Risky Puah Kaho - Payment Module
 * Verifies the /payments/verify and /payments/{id}/status IFA endpoints
 * consumed by the Election and Candidate modules respectively.
 */
final class PaymentWebServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'payment.api.key' => 'payment-provider-secret',
            'payment.candidate_api.key' => 'candidate-consumer-secret',
        ]);
    }

    public function test_it_confirms_a_new_hosting_payment_and_persists_it(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Faculty Council Election',
            'position_sought' => 'Faculty Representative',
            'status' => 'PENDING_PAYMENT',
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $response = $this->withHeader('X-API-Key', 'payment-provider-secret')
            ->postJson('/api/v1/payments/verify', [
                'requestID' => 'EL-PAYMENT-0001',
                'timestamp' => now()->toAtomString(),
                'paymentReference' => 'VT-HOST-PAID-001',
                'purpose' => 'ELECTION_HOSTING',
                'payerId' => $organizer->getKey(),
                'electionId' => $election->getKey(),
                'expectedAmount' => 100,
                'currency' => 'MYR',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'EL-PAYMENT-0001')
            ->assertJsonPath('data.paymentStatus', 'CONFIRMED')
            ->assertJsonPath('data.paymentReference', 'VT-HOST-PAID-001')
            ->assertJsonPath('data.purpose', 'ELECTION_HOSTING')
            ->assertJsonPath('data.payerId', $organizer->getKey());

        $this->assertDatabaseHas('payment', [
            'election_id' => $election->getKey(),
            'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
            'status' => 'Paid',
            'provider_reference' => 'VT-HOST-PAID-001',
        ]);
    }

    public function test_it_reports_a_declined_reference_as_failed(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Faculty Council Election',
            'position_sought' => 'Faculty Representative',
            'status' => 'PENDING_PAYMENT',
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $this->withHeader('X-API-Key', 'payment-provider-secret')
            ->postJson('/api/v1/payments/verify', [
                'requestID' => 'EL-PAYMENT-0002',
                'timestamp' => now()->toAtomString(),
                'paymentReference' => 'VT-HOST-DECLINE-FAIL',
                'purpose' => 'ELECTION_HOSTING',
                'payerId' => $organizer->getKey(),
                'electionId' => $election->getKey(),
                'expectedAmount' => 100,
                'currency' => 'MYR',
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', 'FAILED');
    }

    public function test_it_finds_an_existing_hosting_payment_by_election_id_without_a_reference(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Election ID Payment Lookup',
            'position_sought' => 'President',
            'status' => Election::PENDING_ADMIN_APPROVAL,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Paid',
            'gateway' => 'idontpay99',
            'organizer_id' => $organizer->getKey(),
            'election_id' => $election->getKey(),
            'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
            'provider_reference' => 'VT-HOST-BY-ELECTION',
        ]);

        $this->withHeader('X-API-Key', 'payment-provider-secret')
            ->postJson('/api/v1/payments/verify', [
                'requestID' => 'EL-PAYMENT-LOOKUP-01',
                'timestamp' => now()->toAtomString(),
                'purpose' => 'ELECTION_HOSTING',
                'payerId' => $organizer->getKey(),
                'electionId' => $election->getKey(),
                'expectedAmount' => 100,
                'currency' => 'MYR',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.paymentId', $payment->getKey())
            ->assertJsonPath('data.electionId', $election->getKey())
            ->assertJsonPath('data.paymentReference', 'VT-HOST-BY-ELECTION')
            ->assertJsonPath('data.paymentStatus', 'CONFIRMED');
    }

    public function test_election_id_lookup_fails_when_no_hosting_payment_exists(): void
    {
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Unpaid Election Lookup',
            'position_sought' => 'President',
            'status' => Election::PENDING_ADMIN_APPROVAL,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        $this->withHeader('X-API-Key', 'payment-provider-secret')
            ->postJson('/api/v1/payments/verify', [
                'requestID' => 'EL-PAYMENT-LOOKUP-02',
                'timestamp' => now()->toAtomString(),
                'purpose' => 'ELECTION_HOSTING',
                'payerId' => $organizer->getKey(),
                'electionId' => $election->getKey(),
                'expectedAmount' => 100,
                'currency' => 'MYR',
            ])
            ->assertNotFound()
            ->assertJsonPath('status', 'F');
    }

    public function test_verify_rejects_an_invalid_api_key(): void
    {
        $this->withHeader('X-API-Key', 'wrong-secret')
            ->postJson('/api/v1/payments/verify', [
                'requestID' => 'EL-PAYMENT-0003',
                'timestamp' => now()->toAtomString(),
                'paymentReference' => 'VT-HOST-PAID-002',
                'purpose' => 'ELECTION_HOSTING',
                'payerId' => 1,
                'electionId' => 1,
                'expectedAmount' => 100,
                'currency' => 'MYR',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'F');
    }

    public function test_verify_fails_closed_for_an_unknown_election(): void
    {
        $this->withHeader('X-API-Key', 'payment-provider-secret')
            ->postJson('/api/v1/payments/verify', [
                'requestID' => 'EL-PAYMENT-0004',
                'timestamp' => now()->toAtomString(),
                'paymentReference' => 'VT-HOST-SMOKE-999',
                'purpose' => 'ELECTION_HOSTING',
                'payerId' => 1,
                'electionId' => 999999,
                'expectedAmount' => 100,
                'currency' => 'MYR',
            ])
            ->assertNotFound()
            ->assertJsonPath('status', 'F');

        $this->assertDatabaseMissing('payment', ['provider_reference' => 'VT-HOST-SMOKE-999']);
    }

    public function test_it_reports_candidate_deposit_status_for_the_owning_candidate(): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Faculty Council Election',
            'position_sought' => 'Faculty Representative',
            'status' => 'NOMINATION_OPEN',
        ]);
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'organizer_id' => $organizer->getKey(),
            'candidate_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->withHeader('X-Payment-Api-Key', 'candidate-consumer-secret')
            ->getJson('/api/v1/payments/'.$payment->getKey().'/status?'.http_build_query([
                'candidateId' => $candidate->getKey(),
                'requestID' => 'can_deposit_0001',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.paymentId', $payment->getKey())
            ->assertJsonPath('data.candidateId', $candidate->getKey())
            ->assertJsonPath('data.paymentStatus', 'CONFIRMED');
    }

    public function test_status_endpoint_fails_closed_for_a_mismatched_candidate(): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create();
        $otherCandidate = User::factory()->create();
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'organizer_id' => $organizer->getKey(),
            'candidate_id' => $candidate->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);

        $this->withHeader('X-Payment-Api-Key', 'candidate-consumer-secret')
            ->getJson('/api/v1/payments/'.$payment->getKey().'/status?'.http_build_query([
                'candidateId' => $otherCandidate->getKey(),
                'requestID' => 'can_deposit_0002',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertNotFound()
            ->assertJsonPath('status', 'F');
    }
}
