<?php

namespace App\Services\CandidateRegistration;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PIC: Wong Shun Bin
 * Stores and serves candidate files only from the configured private disk.
 */
final class CandidateFileStorage
{
    /**
     * @return array{path: string, mime: string}
     */
    public function storePhoto(UploadedFile $photo): array
    {
        return [
            'path' => $this->store($photo, $this->photoDirectory()),
            'mime' => $photo->getMimeType() ?: 'application/octet-stream',
        ];
    }

    /**
     * Delete only paths managed by this module. Invalid or foreign paths are ignored.
     *
     * @param  iterable<mixed>  $paths
     */
    public function deleteMany(iterable $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $managedPath = $this->managedPath($path, [$this->photoDirectory()]);

            if ($managedPath !== null) {
                $this->disk()->delete($managedPath);
            }
        }
    }

    public function inlinePhoto(string $path, string $mime): StreamedResponse
    {
        $managedPath = $this->managedPath($path, [$this->photoDirectory()]);
        abort_if($managedPath === null || ! $this->disk()->exists($managedPath), 404);

        return $this->disk()->response($managedPath, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function store(UploadedFile $file, string $directory): string
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        if (! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            throw new RuntimeException('The uploaded file has an unsafe extension.');
        }

        $storedPath = $this->disk()->putFileAs(
            $directory,
            $file,
            Str::random(48).'.'.$extension,
        );

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $storedPath;
    }

    /**
     * @param  list<string>  $directories
     */
    private function managedPath(string $path, array $directories): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        if (
            $normalized === ''
            || preg_match('#(^|/)\.{1,2}(/|$)#', $normalized) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1
        ) {
            return null;
        }

        foreach ($directories as $directory) {
            $directory = trim(str_replace('\\', '/', $directory), '/');

            if ($directory !== '' && str_starts_with($normalized, $directory.'/')) {
                return $normalized;
            }
        }

        return null;
    }

    private function photoDirectory(): string
    {
        return $this->configuredDirectory('photos_directory', 'candidate/photos');
    }

    private function configuredDirectory(string $key, string $default): string
    {
        $directory = trim((string) config("candidate.{$key}", $default), '/\\ ');

        if ($directory === '' || preg_match('#(^|/)\.{1,2}(/|$)#', str_replace('\\', '/', $directory)) === 1) {
            throw new RuntimeException("Candidate storage directory [{$key}] is invalid.");
        }

        return str_replace('\\', '/', $directory);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('candidate.disk', 'local'));
    }
}
