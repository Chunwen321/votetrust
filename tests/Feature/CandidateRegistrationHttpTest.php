<?php

namespace Tests\Feature;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\Services\CandidateRegistration\RealRegistrationService;
use App\Services\CandidateRegistration\RegistrationProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Verifies candidate HTTP ownership boundaries and cancellation cleanup.
 */
final class CandidateRegistrationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_member_can_open_the_nomination_form(): void
    {
        [$candidate, $election] = $this->candidateAndOpenElection();

        $this->actingAs($candidate)
            ->get(route('candidate.applications.create'))
            ->assertOk()
            ->assertSee($election->title);
    }

    public function test_the_http_form_submits_through_the_bound_registration_proxy(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();

        $this->actingAs($candidate)
            ->post(route('candidate.applications.store'), [
                'election_id' => $election->getKey(),
                'manifesto' => str_repeat('A clear commitment to student representation. ', 2),
                'candidate_introduction' => 'I am a second-year student who enjoys serving the student community.',
                'qualifications' => 'Previous committee experience.',
                'reason_for_candidacy' => 'I want to improve communication between students and their representatives.',
                'profile_photo' => UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
                'documents' => [
                    UploadedFile::fake()->create('support.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('candidate.profile.show'));

        $this->assertDatabaseHas('candidacy', [
            'user_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
            'status' => 'Pending',
            'candidate_introduction' => 'I am a second-year student who enjoys serving the student community.',
            'qualifications' => 'Previous committee experience.',
            'reason_for_candidacy' => 'I want to improve communication between students and their representatives.',
        ]);
    }

    public function test_private_files_are_available_only_to_the_application_owner(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $intruder = User::factory()->create();
        $candidacy = $this->application($candidate, $election);
        $document = $candidacy->documents()->firstOrFail();

        $this->actingAs($intruder)
            ->get(route('candidate.applications.photo', $candidacy))
            ->assertForbidden();
        $this->get(route('candidate.applications.documents.download', [$candidacy, $document]))
            ->assertForbidden();

        $photoResponse = $this->actingAs($candidate)
            ->get(route('candidate.applications.photo', $candidacy));
        $photoResponse->assertOk();
        $this->assertStringContainsString('private', (string) $photoResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $photoResponse->headers->get('Cache-Control'));
        $this->get(route('candidate.applications.documents.download', [$candidacy, $document]))
            ->assertOk()
            ->assertDownload('support.pdf');
    }

    public function test_only_the_owner_can_cancel_and_cleanup_an_unpaid_application(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $intruder = User::factory()->create();
        $candidacy = $this->application($candidate, $election);
        $candidacy->load(['payment', 'documents']);
        $this->fakePaymentStatus('PENDING');
        $photoPath = $candidacy->profile_photo;
        $documentPath = $candidacy->documents->first()->stored_name;

        $this->actingAs($intruder)
            ->post(route('candidate.applications.cancel', $candidacy))
            ->assertForbidden();
        $this->assertSame('Pending', $candidacy->fresh()->status);

        $this->actingAs($candidate)
            ->post(route('candidate.applications.cancel', $candidacy))
            ->assertRedirect(route('candidate.profile.show'));

        $candidacy->refresh();
        $this->assertSame('Cancelled', $candidacy->status);
        $this->assertNull($candidacy->profile_photo);
        $this->assertSame('Pending', $candidacy->payment->fresh()->status);
        $this->assertDatabaseMissing('candidate_document', ['candidacy_id' => $candidacy->getKey()]);
        Storage::disk('candidate-test')->assertMissing($photoPath);
        Storage::disk('candidate-test')->assertMissing($documentPath);
    }

    public function test_pending_profile_displays_position_application_and_payment_status(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $candidacy = $this->application($candidate, $election);
        $this->fakePaymentStatus('CONFIRMED');

        $this->actingAs($candidate)
            ->get(route('candidate.profile.show'))
            ->assertOk()
            ->assertSee('Position:')
            ->assertSee('Application:')
            ->assertSee('Confirmed')
            ->assertSee('Candidate Introduction:')
            ->assertSee('Qualifications / Experience:')
            ->assertSee('Reason for Candidacy:')
            ->assertSee(route('candidate.payments.show', $candidacy->payment_id));

        Http::assertSent(fn (ClientRequest $request): bool => str_contains(
            $request->url(),
            "/api/v1/payments/{$candidacy->payment_id}/status",
        ));
    }

    public function test_profile_hides_new_application_when_no_eligible_election_remains(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $this->application($candidate, $election);

        $this->actingAs($candidate)
            ->get(route('candidate.profile.show'))
            ->assertOk()
            ->assertDontSee('New application');
    }

    public function test_approved_profile_displays_candidate_background_details(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $candidacy = $this->application($candidate, $election);
        $candidacy->update(['status' => Candidacy::APPROVED]);

        $this->actingAs($candidate)
            ->get(route('candidate.profile.show'))
            ->assertOk()
            ->assertSee('Candidate Introduction:')
            ->assertSee('Qualifications / Experience:')
            ->assertSee('Previous committee experience.')
            ->assertSee('Reason for Candidacy:');
    }

    public function test_profile_only_displays_the_most_recent_application(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        $candidate = User::factory()->create();
        $firstElection = $this->candidateAndOpenElection()[1];
        $secondElection = $this->candidateAndOpenElection()[1];
        $firstElection->update(['title' => 'Older Candidate Election']);
        $secondElection->update(['title' => 'Latest Candidate Election']);

        $older = $this->application($candidate, $firstElection);
        $older->update(['registered_at' => now()->subDay()]);
        $latest = $this->application($candidate, $secondElection);
        $latest->update(['registered_at' => now()]);

        $this->actingAs($candidate)
            ->get(route('candidate.profile.show'))
            ->assertOk()
            ->assertSee('Latest Candidate Election')
            ->assertDontSee('Older Candidate Election');
    }

    private function application(User $candidate, Election $election): Candidacy
    {
        $service = new RegistrationProxy($this->app->make(RealRegistrationService::class));

        return $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            [
                'manifesto' => str_repeat('A clear commitment to student representation. ', 2),
                'candidate_introduction' => 'I am a second-year student who enjoys serving the student community.',
                'qualifications' => 'Previous committee experience.',
                'reason_for_candidacy' => 'I want to improve communication between students and their representatives.',
            ],
            [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        )->load('documents');
    }

    /**
     * @return array{User, Election}
     */
    private function candidateAndOpenElection(): array
    {
        $candidate = User::factory()->create();
        $organizer = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Student Council Election',
            'position_sought' => 'Student Representative',
            'status' => 'NOMINATION_OPEN',
            'nomination_start' => now()->subDay(),
            'nomination_end' => now()->addDay(),
        ]);

        return [$candidate, $election];
    }

    private function fakePaymentStatus(string $status): void
    {
        config([
            'candidate.services.payment.base_url' => 'https://payment.test',
            'candidate.services.payment.api_key' => 'payment-consumer-secret',
        ]);

        Http::fake(function (ClientRequest $request) use ($status) {
            preg_match('#/payments/(\d+)/status#', $request->url(), $matches);

            return Http::response([
                'status' => 'S',
                'message' => 'Candidate deposit status retrieved.',
                'requestID' => $request['requestID'],
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'paymentId' => (int) ($matches[1] ?? 0),
                    'candidateId' => $request['candidateId'],
                    'paymentStatus' => $status,
                ],
            ]);
        });
    }
}
