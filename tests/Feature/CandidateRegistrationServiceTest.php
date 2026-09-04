<?php

namespace Tests\Feature;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\Services\CandidateRegistration\RealRegistrationService;
use App\Services\CandidateRegistration\RegistrationProxy;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Exercises registration, private storage, deposits, and safe cancelled reapplication.
 */
final class CandidateRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_candidate_with_all_answers_a_photo_and_a_pending_deposit(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();

        $candidacy = $this->service()->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        );

        $candidacy->load('payment');
        $this->assertSame('Pending', $candidacy->status);
        $this->assertSame('Pending', $candidacy->payment->status);
        $this->assertSame($candidate->getKey(), $candidacy->payment->candidate_id);
        $this->assertSame(trim($this->validInput()['manifesto']), $candidacy->manifesto);
        $this->assertSame($this->validInput()['candidate_introduction'], $candidacy->candidate_introduction);
        $this->assertSame($this->validInput()['qualifications'], $candidacy->qualifications);
        $this->assertSame($this->validInput()['reason_for_candidacy'], $candidacy->reason_for_candidacy);
        $this->assertFalse(Schema::hasTable('candidate_document'));
        Storage::disk('candidate-test')->assertExists($candidacy->profile_photo);
    }

    public function test_rejected_reapplication_reuses_the_record_and_removes_replaced_files(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $service = $this->service();

        $first = $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg'),
        );
        $first->load('payment');
        $oldPhoto = $first->profile_photo;
        $first->payment->update(['status' => 'Failed']);
        $first->update(['status' => Candidacy::REJECTED]);

        $replacement = $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
        );
        $replacement->load('payment');

        $this->assertSame($first->getKey(), $replacement->getKey());
        $this->assertSame('Pending', $replacement->status);
        $this->assertFalse(Schema::hasTable('candidate_document'));
        $this->assertDatabaseCount('payment', 2);
        Storage::disk('candidate-test')->assertMissing($oldPhoto);
        Storage::disk('candidate-test')->assertExists($replacement->profile_photo);
    }

    public function test_cancelled_application_can_be_resubmitted_using_the_same_record(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $service = $this->service();

        $first = $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg'),
        );
        $first->update([
            'status' => Candidacy::CANCELLED,
            'profile_photo' => null,
            'profile_photo_mime' => null,
        ]);

        $replacement = $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            UploadedFile::fake()->create('replacement.jpg', 100, 'image/jpeg'),
        );

        $this->assertSame($first->getKey(), $replacement->getKey());
        $this->assertSame(Candidacy::PENDING, $replacement->status);
        $this->assertDatabaseCount('candidacy', 1);
        $this->assertDatabaseCount('payment', 2);
        Storage::disk('candidate-test')->assertExists($replacement->profile_photo);
    }

    public function test_a_private_election_rejects_a_direct_public_application(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $election->update([
            'eligibility_rules' => EligibilityRules::privateElection()->toStorage(),
        ]);

        try {
            $this->service()->apply(
                $candidate->getKey(),
                $election->getKey(),
                $this->validInput(),
                UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
            );
            self::fail('A private election accepted a public candidate application.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'This private election does not accept public candidate applications.',
                $exception->errors()['election_id'][0],
            );
        }

        $this->assertDatabaseMissing('candidacy', [
            'user_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
        ]);
    }

    private function service(): RegistrationProxy
    {
        return new RegistrationProxy($this->app->make(RealRegistrationService::class));
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

    /**
     * @return array{manifesto: string, candidate_introduction: string, qualifications: string, reason_for_candidacy: string}
     */
    private function validInput(): array
    {
        return [
            'manifesto' => str_repeat('A clear commitment to student representation. ', 2),
            'candidate_introduction' => 'I am a second-year student who enjoys serving the student community.',
            'qualifications' => 'Previous committee experience.',
            'reason_for_candidacy' => 'I want to improve communication between students and their representatives.',
        ];
    }
}
