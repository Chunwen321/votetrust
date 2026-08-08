<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Models\CandidateDocument;
use App\Services\CandidateRegistration\CandidateFileStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PIC: Wong Shun Bin
 * Downloads a private supporting document only for its authenticated owner.
 */
final class CandidateDocumentController extends Controller
{
    public function download(
        Request $request,
        Candidacy $candidacy,
        CandidateDocument $document,
        CandidateFileStorage $fileStorage,
    ): StreamedResponse {
        abort_unless((int) $candidacy->candidate_id === (int) $request->user()?->getAuthIdentifier(), 403);
        abort_unless((int) $document->candidacy_id === (int) $candidacy->getKey(), 404);

        return $fileStorage->downloadDocument(
            (string) $document->stored_name,
            (string) $document->original_name,
            is_string($document->mime_type) ? $document->mime_type : 'application/octet-stream',
        );
    }
}
