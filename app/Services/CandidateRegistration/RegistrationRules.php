<?php

namespace App\Services\CandidateRegistration;

/**
 * PIC: Wong Shun Bin
 * Central validation rules for candidate applications and their private files.
 */
final class RegistrationRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function application(): array
    {
        $maximumDocuments = max(1, (int) config('candidate.max_documents', 5));
        $maximumPhotoKilobytes = max(1, (int) config('candidate.max_photo_kilobytes', 2048));
        $maximumDocumentKilobytes = max(1, (int) config('candidate.max_document_kilobytes', 5120));

        return [
            'position_sought' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\p{L}\p{N} .,&()\/-]+$/u',
            ],
            'manifesto' => ['required', 'string', 'min:50', 'max:5000'],
            'candidate_introduction' => ['required', 'string', 'min:20', 'max:1000'],
            'qualifications' => ['nullable', 'string', 'max:1000'],
            'reason_for_candidacy' => ['required', 'string', 'min:20', 'max:1000'],
            'profile_photo' => [
                'required',
                'file',
                "max:{$maximumPhotoKilobytes}",
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
                'extensions:jpg,jpeg,png',
            ],
            'documents' => ['nullable', 'array', "max:{$maximumDocuments}"],
            'documents.*' => [
                'file',
                "max:{$maximumDocumentKilobytes}",
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'extensions:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
