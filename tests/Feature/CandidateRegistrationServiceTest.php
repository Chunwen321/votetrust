<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\User;
use App\Services\CandidateRegistration\RealRegistrationService;
use App\Services\CandidateRegistration\RegistrationProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Exercises registration, private storage, deposits, and safe cancelled reapplication.
 */
final class CandidateRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_candidate_with_private_files_and_a_pending_deposit(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();

        $candidacy = $this->service()->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            [UploadedFile::fake()->create('support.pdf', 100, 'application/pdf')],
            UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        );

        $candidacy->load(['payment', 'documents']);
        $this->assertSame('Pending', $candidacy->status);
        $this->assertSame('Pending', $candidacy->payment->status);
        $this->assertSame($candidate->getKey(), $candidacy->payment->candidate_id);
        $this->assertCount(1, $candidacy->documents);
        Storage::disk('candidate-test')->assertExists($candidacy->profile_photo);
        Storage::disk('candidate-test')->assertExists($candidacy->documents->first()->stored_name);
    }

    public function test_cancelled_reapplication_reuses_the_record_and_removes_replaced_files(): void
    {
        Storage::fake('candidate-test');
        config(['candidate.disk' => 'candidate-test']);
        [$candidate, $election] = $this->candidateAndOpenElection();
        $service = $this->service();

        $first = $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            [UploadedFile::fake()->create('old.pdf', 100, 'application/pdf')],
            UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg'),
        );
        $first->load(['payment', 'documents']);
        $oldPhoto = $first->profile_photo;
        $oldDocument = $first->documents->first()->stored_name;
        $first->payment->update(['status' => 'Failed']);
        $first->update(['status' => 'Cancelled']);

        $replacement = $service->apply(
            $candidate->getKey(),
            $election->getKey(),
            $this->validInput(),
            [UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')],
            UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
        );
        $replacement->load(['payment', 'documents']);

        $this->assertSame($first->getKey(), $replacement->getKey());
        $this->assertSame('Pending', $replacement->status);
        $this->assertCount(1, $replacement->documents);
        $this->assertDatabaseCount('payment', 2);
        Storage::disk('candidate-test')->assertMissing($oldPhoto);
        Storage::disk('candidate-test')->assertMissing($oldDocument);
        Storage::disk('candidate-test')->assertExists($replacement->profile_photo);
        Storage::disk('candidate-test')->assertExists($replacement->documents->first()->stored_name);
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
            'status' => 'NOMINATION_OPEN',
            'nomination_start' => now()->subDay(),
            'nomination_end' => now()->addDay(),
        ]);

        return [$candidate, $election];
    }

    /**
     * @return array{position_sought: string, manifesto: string, supporting_details: string}
     */
    private function validInput(): array
    {
        return [
            'position_sought' => 'Student Representative',
            'manifesto' => str_repeat('A clear commitment to student representation. ', 2),
            'supporting_details' => 'Previous committee experience.',
        ];
    }
}
