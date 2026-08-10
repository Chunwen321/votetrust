<?php

namespace App\Http\Controllers;

use App\Contracts\RegistrationService;
use App\Http\Requests\StoreCandidacyRequest;
use App\Models\Election;
use App\ValueObjects\EligibilityRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * PIC: Wong Shun Bin
 * Displays the nomination form and submits candidate applications.
 */
final class CandidateRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $now = now();
        $elections = Election::query()
            ->where('status', 'NOMINATION_OPEN')
            ->where(function ($query) use ($now): void {
                $query->whereNull('nomination_start')
                    ->orWhere('nomination_start', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('nomination_end')
                    ->orWhere('nomination_end', '>=', $now);
            })
            ->orderBy('nomination_end')
            ->orderBy('title')
            ->get()
            ->filter(fn (Election $election): bool => EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PUBLIC)
            ->values();

        return view('candidate.registration.create', [
            'elections' => $elections,
            'selectedElectionId' => $request->integer('election_id') ?: null,
        ]);
    }

    public function store(
        StoreCandidacyRequest $request,
        RegistrationService $service,
    ): RedirectResponse {
        $documents = $request->file('documents', []);
        $documents = is_array($documents) ? array_values($documents) : [];
        $photo = $request->file('profile_photo');

        abort_unless($photo instanceof UploadedFile, 422);

        $service->apply(
            (int) $request->user()?->getAuthIdentifier(),
            (int) $request->integer('election_id'),
            $request->safe()->only([
                'position_sought',
                'manifesto',
                'supporting_details',
            ]),
            $documents,
            $photo,
        );

        return redirect()
            ->route('candidate.profile.show')
            ->with('success', 'Your application was submitted with Pending status.');
    }
}
