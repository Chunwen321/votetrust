<?php

namespace App\Http\Controllers;

use App\Contracts\RegistrationService;
use App\Http\Requests\StoreCandidacyRequest;
use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\Services\CandidateRegistration\CandidateApplicationAccess;
use App\ValueObjects\EligibilityRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PIC: Wong Shun Bin
 * Displays the nomination form and submits candidate applications.
 */
final class CandidateRegistrationController extends Controller
{
    public function __construct(private readonly CandidateApplicationAccess $applicationAccess) {}

    public function create(Request $request, Election $election): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(
            EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PUBLIC,
            404,
        );

        $unavailableReason = $this->applicationAccess->unavailableReason($user, $election);
        if ($unavailableReason !== null) {
            $existingApplication = Candidacy::query()
                ->where('user_id', $user->getKey())
                ->where('election_id', $election->getKey())
                ->where('status', '!=', Candidacy::REJECTED)
                ->first();

            if ($existingApplication instanceof Candidacy) {
                return redirect()
                    ->route('candidate.applications.show', $existingApplication)
                    ->with('notice', $unavailableReason);
            }

            return redirect()
                ->route('elections.show', $election)
                ->with('error', $unavailableReason);
        }

        return view('candidate.registration.create', [
            'election' => $election,
            'isReapplication' => Candidacy::query()
                ->where('user_id', $user->getKey())
                ->where('election_id', $election->getKey())
                ->where('status', Candidacy::REJECTED)
                ->exists(),
        ]);
    }

    public function store(
        StoreCandidacyRequest $request,
        Election $election,
        RegistrationService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $unavailableReason = $this->applicationAccess->unavailableReason($user, $election);
        if ($unavailableReason !== null) {
            throw ValidationException::withMessages(['application' => $unavailableReason]);
        }

        $photo = $request->file('profile_photo');

        abort_unless($photo instanceof UploadedFile, 422);

        $application = $service->apply(
            (int) $user->getAuthIdentifier(),
            (int) $election->getKey(),
            [
                // Copy the validated values explicitly so every application answer
                // reaches the persistence service as a plain array.
                'manifesto' => (string) $request->validated('manifesto'),
                'candidate_introduction' => (string) $request->validated('candidate_introduction'),
                'qualifications' => $request->validated('qualifications'),
                'reason_for_candidacy' => (string) $request->validated('reason_for_candidacy'),
            ],
            $photo,
        );

        return redirect()
            ->route('candidate.payments.show', $application->payment_id)
            ->with('success', 'Your application was submitted. Complete the security deposit to continue.');
    }
}
