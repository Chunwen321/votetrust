<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Models\CandidateDocument;
use App\Services\CandidateRegistration\CandidateFileStorage;
use App\Services\CandidateRegistration\PaymentStatusClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PIC: Wong Shun Bin
 * Cancels an owned, unpaid application and removes its private uploads.
 */
final class CandidacyCancellationController extends Controller
{
    public function __invoke(
        Request $request,
        Candidacy $candidacy,
        CandidateFileStorage $fileStorage,
        PaymentStatusClient $paymentStatusClient,
    ): RedirectResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();
        abort_unless((int) $candidacy->candidate_id === $userId, 403);

        if ($candidacy->payment_id === null) {
            throw ValidationException::withMessages([
                'application' => 'The application has no Payment module reference.',
            ]);
        }

        $paymentStatus = $paymentStatusClient->fetch((int) $candidacy->payment_id, $userId);
        if (! $paymentStatus->available) {
            throw ValidationException::withMessages([
                'application' => 'Cancellation is disabled until Payment status can be verified.',
            ]);
        }

        if (! $paymentStatus->isPending()) {
            throw ValidationException::withMessages([
                'application' => 'Only an unpaid pending application can be cancelled.',
            ]);
        }

        $paymentId = (int) $candidacy->payment_id;
        $obsoletePaths = DB::transaction(function () use ($candidacy, $userId, $paymentId): array {
            $lockedCandidacy = Candidacy::query()
                ->whereKey($candidacy->getKey())
                ->where('candidate_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCandidacy->status !== 'Pending' || (int) $lockedCandidacy->payment_id !== $paymentId) {
                throw ValidationException::withMessages([
                    'application' => 'Only an unpaid pending application can be cancelled.',
                ]);
            }

            $paths = CandidateDocument::query()
                ->where('candidacy_id', $lockedCandidacy->getKey())
                ->lockForUpdate()
                ->pluck('stored_name')
                ->filter(fn ($path): bool => is_string($path) && $path !== '')
                ->values()
                ->all();

            if (is_string($lockedCandidacy->profile_photo) && $lockedCandidacy->profile_photo !== '') {
                $paths[] = $lockedCandidacy->profile_photo;
            }

            CandidateDocument::query()
                ->where('candidacy_id', $lockedCandidacy->getKey())
                ->delete();

            $lockedCandidacy->status = 'Cancelled';
            $lockedCandidacy->profile_photo = null;
            $lockedCandidacy->profile_photo_mime = null;
            $lockedCandidacy->save();

            return $paths;
        });

        $fileStorage->deleteMany($obsoletePaths);

        return redirect()
            ->route('candidate.profile.show')
            ->with('success', 'Your application was cancelled.');
    }
}
