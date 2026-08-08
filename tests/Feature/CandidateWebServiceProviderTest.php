<?php

namespace Tests\Feature;

use App\Models\Candidacy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Verifies the authenticated Candidate/Organisation IFA exposed to Payment.
 */
final class CandidateWebServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_retrieve_minimal_candidate_and_organisation_information(): void
    {
        config(['candidate.api.key' => 'candidate-provider-secret']);
        [$candidate, $organizer, $election] = $this->candidateApplication();

        $response = $this->withHeader('X-Candidate-Api-Key', 'candidate-provider-secret')
            ->getJson(route('api.candidates.payment-information', [
                'candidate' => $candidate->getKey(),
                'electionId' => $election->getKey(),
                'requestID' => 'payment_req_1234',
                'timestamp' => now()->toAtomString(),
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'payment_req_1234')
            ->assertJsonPath('data.candidate.candidateId', $candidate->getKey())
            ->assertJsonPath('data.candidate.name', 'Candidate Person')
            ->assertJsonPath('data.organisation.organisationId', $organizer->getKey())
            ->assertJsonPath('data.organisation.name', 'Election Organisation')
            ->assertJsonPath('data.candidacy.electionId', $election->getKey())
            ->assertJsonStructure(['timestamp', 'data' => ['candidate', 'organisation', 'candidacy']])
            ->assertJsonMissingPath('data.candidate.icNumber');
    }

    public function test_candidate_information_service_rejects_an_invalid_key_with_ifa_envelope(): void
    {
        config(['candidate.api.key' => 'candidate-provider-secret']);
        [$candidate, , $election] = $this->candidateApplication();

        $this->withHeader('X-Candidate-Api-Key', 'incorrect-secret')
            ->getJson(route('api.candidates.payment-information', [
                'candidate' => $candidate->getKey(),
                'electionId' => $election->getKey(),
                'requestID' => 'payment_req_1235',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertUnauthorized()
            ->assertJsonPath('status', 'E')
            ->assertJsonPath('requestID', 'payment_req_1235')
            ->assertJsonStructure(['timestamp', 'errors']);
    }

    public function test_candidate_information_service_rejects_a_stale_request(): void
    {
        config([
            'candidate.api.key' => 'candidate-provider-secret',
            'candidate.api.clock_skew_seconds' => 300,
        ]);
        [$candidate, , $election] = $this->candidateApplication();

        $this->withHeader('X-Candidate-Api-Key', 'candidate-provider-secret')
            ->getJson(route('api.candidates.payment-information', [
                'candidate' => $candidate->getKey(),
                'electionId' => $election->getKey(),
                'requestID' => 'payment_req_1236',
                'timestamp' => now()->subMinutes(10)->toAtomString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'E')
            ->assertJsonValidationErrors('timestamp');
    }

    /** @return array{Candidate, User, Election} */
    private function candidateApplication(): array
    {
        $candidateUser = User::factory()->create([
            'name' => 'Candidate Person',
            'full_name' => 'Candidate Person',
            'email' => 'candidate@example.test',
        ]);
        $organizer = User::factory()->create([
            'name' => 'Election Organisation',
            'full_name' => 'Election Organisation',
            'email' => 'organisation@example.test',
        ]);
        $candidate = Candidate::query()->create([
            'user_id' => $candidateUser->getKey(),
            'full_name' => 'Candidate Person',
            'email' => 'candidate@example.test',
        ]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Student Council Election',
            'status' => 'NOMINATION_OPEN',
        ]);

        Candidacy::query()->create([
            'candidate_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
            'status' => 'Pending',
            'registered_at' => now(),
        ]);

        return [$candidate, $organizer, $election];
    }
}
