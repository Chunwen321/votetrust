<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Models\Election;
use App\Services\CandidateRegistration\CandidateFileStorage;
use App\ValueObjects\EligibilityRules;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PIC: Wong Shun Bin
 * Streams candidate photos from private local storage after access checks.
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

    public function electionCandidate(
        Request $request,
        Election $election,
        Candidacy $candidacy,
        CandidateFileStorage $fileStorage,
    ): StreamedResponse {
        abort_unless(
            (int) $candidacy->election_id === (int) $election->getKey()
            && $candidacy->status === Candidacy::APPROVED,
            404,
        );

        $userId = (int) $request->user()?->getAuthIdentifier();
        $published = in_array($election->status, [
            Election::NOMINATION_OPEN,
            Election::VOTING_OPEN,
            Election::CLOSED,
        ], true);
        $historical = in_array($election->status, [
            Election::CANCELLED,
            Election::EXPIRED,
            Election::REJECTED,
        ], true);
        $publiclyVisible = $published
            && EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PUBLIC;
        $viewerIsCandidate = ($published || $historical) && Candidacy::query()
            ->where('election_id', $election->getKey())
            ->where('user_id', $userId)
            ->exists();

        abort_unless(
            (int) $election->organizer_id === $userId || $publiclyVisible || $viewerIsCandidate,
            404,
        );
        abort_if(! is_string($candidacy->profile_photo) || $candidacy->profile_photo === '', 404);

        return $fileStorage->inlinePhoto(
            $candidacy->profile_photo,
            is_string($candidacy->profile_photo_mime)
                ? $candidacy->profile_photo_mime
                : 'application/octet-stream',
        );
    }
}
