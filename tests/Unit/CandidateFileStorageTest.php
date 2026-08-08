<?php

namespace Tests\Unit;

use App\Services\CandidateRegistration\CandidateFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Verifies candidate files stay on the configured private disk and cleanup is scoped.
 */
final class CandidateFileStorageTest extends TestCase
{
    public function test_it_stores_and_deletes_managed_candidate_files(): void
    {
        Storage::fake('candidate-test');
        config([
            'candidate.disk' => 'candidate-test',
            'candidate.photos_directory' => 'candidate/photos',
            'candidate.documents_directory' => 'candidate/documents',
        ]);

        $storage = new CandidateFileStorage;
        $photo = $storage->storePhoto(
            UploadedFile::fake()->create('portrait.jpg', 100, 'image/jpeg'),
        );
        $document = $storage->storeDocument(
            UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'),
        );

        Storage::disk('candidate-test')->assertExists($photo['path']);
        Storage::disk('candidate-test')->assertExists($document['path']);
        $this->assertStringStartsWith('candidate/photos/', $photo['path']);
        $this->assertStringStartsWith('candidate/documents/', $document['path']);
        $this->assertSame('statement.pdf', $document['original_name']);

        $storage->deleteMany([$photo['path'], $document['path']]);

        Storage::disk('candidate-test')->assertMissing($photo['path']);
        Storage::disk('candidate-test')->assertMissing($document['path']);
    }

    public function test_cleanup_never_deletes_paths_outside_candidate_storage(): void
    {
        Storage::fake('candidate-test');
        config([
            'candidate.disk' => 'candidate-test',
            'candidate.photos_directory' => 'candidate/photos',
            'candidate.documents_directory' => 'candidate/documents',
        ]);
        Storage::disk('candidate-test')->put('unrelated/keep.txt', 'keep');

        (new CandidateFileStorage)->deleteMany(['unrelated/keep.txt', '../keep.txt']);

        Storage::disk('candidate-test')->assertExists('unrelated/keep.txt');
    }
}
