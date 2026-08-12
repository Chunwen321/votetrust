<?php

namespace Tests\Unit;

use App\Services\CandidateRegistration\RegistrationRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Covers the module's defense-in-depth application and file rules.
 */
final class CandidateRegistrationRulesTest extends TestCase
{
    public function test_valid_candidate_application_passes_domain_rules(): void
    {
        config(['candidate.max_documents' => 5]);

        $validator = Validator::make([
            'manifesto' => str_repeat('A clear commitment to student representation. ', 2),
            'candidate_introduction' => 'I am a second-year student who enjoys serving the student community.',
            'qualifications' => 'Previous committee experience.',
            'reason_for_candidacy' => 'I want to improve communication between students and their representatives.',
            'profile_photo' => UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
            'documents' => [
                UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'),
            ],
        ], RegistrationRules::application());

        $this->assertTrue($validator->passes(), $validator->errors()->first());
    }

    public function test_short_manifesto_and_executable_document_are_rejected(): void
    {
        $validator = Validator::make([
            'manifesto' => 'Too short.',
            'profile_photo' => UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
            'documents' => [
                UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ],
        ], RegistrationRules::application());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('manifesto', $validator->errors()->toArray());
        $this->assertArrayHasKey('documents.0', $validator->errors()->toArray());
    }
}
