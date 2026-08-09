<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Services\CandidateRegistration\CandidateFileStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PIC: Wong Shun Bin
 * Streams a private candidate photo only to its authenticated owner.
 */
final class CandidatePhotoController extends Controller
{
    public function show(
        Request $request,
        Candidacy $candidacy,
        CandidateFileStorage $fileStorage,
    ): StreamedResponse {
        abort_unless((int) $candidacy->user_id === (int) $request->user()?->getAuthIdentifier(), 403);
        abort_if(! is_string($candidacy->profile_photo) || $candidacy->profile_photo === '', 404);

        return $fileStorage->inlinePhoto(
            $candidacy->profile_photo,
            is_string($candidacy->profile_photo_mime)
                ? $candidacy->profile_photo_mime
                : 'application/octet-stream',
        );
    }
}
