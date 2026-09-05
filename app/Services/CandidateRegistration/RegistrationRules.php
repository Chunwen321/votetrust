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
        $maximumPhotoKilobytes = max(1, (int) config('candidate.max_photo_kilobytes', 2048));

        return [
            'manifesto' => ['required', 'string', 'min:50', 'max:5000'],
            'candidate_introduction' => ['required', 'string', 'min:20', 'max:1000'],
            'qualifications' => ['nullable', 'string', 'max:1000'],
            'reason_for_candidacy' => ['required', 'string', 'min:20', 'max:1000'],
            /* Before secure practice coding - Secure File Upload
            'profile_photo' => ['required', 'file'],
            */

            /* After secure practice coding - Currently running code */
            'profile_photo' => [
                'required',
                'file',
                "max:{$maximumPhotoKilobytes}",
                'mimes:jpg,jpeg,png',
                'mimetypes:image/jpeg,image/png',
                'extensions:jpg,jpeg,png',
            ],
        ];
    }
}
