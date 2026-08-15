<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConcurrencyException;
use App\Http\Requests\ImportPrivateVoterListRequest;
use App\Http\Requests\ReviewCandidatesRequest;
use App\Http\Requests\StoreElectionRequest;
use App\Http\Requests\TransitionElectionRequest;
use App\Http\Requests\UpdateElectionRequest;
use App\Http\Requests\UpdatePrivateVoterSelectionRequest;
use App\Models\Election;
use App\Services\ElectionService;
use App\Services\PrivateVoterRosterFile;
use App\Services\User\VotingParticipationClient;
use App\Services\VoterModuleClient;
use App\Services\VoterService;
use App\States\ElectionStatus;
use App\Support\ElectionCardMilestone;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionService $service,
        private readonly VoterService $voters,
        private readonly VotingParticipationClient $voting,
        private readonly PrivateVoterRosterFile $privateVoterRosterFile,
        private readonly VoterModuleClient $voterModule,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($this->service->isAdmin($user)) {
            return redirect()->route('admin.elections.index');
        }

        $enrollments = $this->voters->enrollmentsForVoter((int) $user->getKey());
        $candidateElections = Election::query()
            ->whereIn('status', $this->publishedStatuses())
            ->where(fn ($query) => $query
                ->whereNull('organizer_id')
                ->orWhere('organizer_id', '!=', $user->getKey()))
            ->with(['candidacies' => fn ($query) => $query->where('user_id', $user->getKey())])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Election $election): bool => $this->isPublic($election)
                || $election->candidacies->isNotEmpty())
            ->values();
        $organizedElections = Election::query()
            ->where('organizer_id', $user->getKey())
            ->latest('updated_at')
            ->get();
        $now = CarbonImmutable::now((string) config('app.timezone'));

        return view('elections.index', [
            'user' => $user,
            'voterEnrollments' => $enrollments,
            'organizedElections' => $organizedElections,
            'candidateElections' => $candidateElections,
            'electionTimezoneLabel' => ElectionCardMilestone::timezoneLabel(),
            'voterMilestones' => $enrollments->mapWithKeys(fn ($enrollment): array => [
                (int) $enrollment->election_id => ElectionCardMilestone::forVoter(
                    (string) $enrollment->election_status,
                    $enrollment->election_voting_start,
                    $enrollment->election_voting_end,
                    $now,
                ),
            ]),
            'candidateMilestones' => $candidateElections->mapWithKeys(fn (Election $election): array => [
                (int) $election->getKey() => ElectionCardMilestone::forCandidate(
                    (string) $election->status,
                    $election->nomination_start,
                    $election->nomination_end,
                    $election->voting_start,
                    $election->voting_end,
                    $now,
                ),
            ]),
            'candidateApplicationsOpen' => $candidateElections->mapWithKeys(fn (Election $election): array => [
                (int) $election->getKey() => $election->status === Election::NOMINATION_OPEN
                    && ($election->nomination_start === null || $election->nomination_start->lte($now))
                    && ($election->nomination_end === null || $election->nomination_end->gte($now)),
            ]),
            'organizerMilestones' => $organizedElections->mapWithKeys(fn (Election $election): array => [
                (int) $election->getKey() => ElectionCardMilestone::forOrganizer(
                    (string) $election->status,
                    $election->nomination_start,
                    $election->nomination_end,
                    $election->voting_start,
                    $election->voting_end,
                    $election->rejected_at,
                    $now,
                ),
            ]),
            'votingStatuses' => $this->voting->statusesForVoter(
                (int) $user->getKey(),
                $enrollments->pluck('election_id')->all(),
            ),
        ]);
    }

    public function create(Request $request): View
    {
        abort_if($this->service->isAdmin($request->user()), 403, 'Administrators cannot create elections.');
        $now = CarbonImmutable::now();

        return view('elections.create', ['defaults' => [
            'nomination_start' => $now->addDay()->format('Y-m-d\TH:i'),
            'nomination_end' => $now->addDays(8)->format('Y-m-d\TH:i'),
            'voting_start' => $now->addDays(9)->format('Y-m-d\TH:i'),
            'voting_end' => $now->addDays(10)->format('Y-m-d\TH:i'),
        ]]);
    }

    public function store(StoreElectionRequest $request): RedirectResponse
    {
        $election = $this->service->create($request->user(), $request->validated());

        return redirect()->route('elections.show', $election)
            ->with('success', 'Election draft created. Review it before continuing to hosting payment.');
    }

    public function show(Request $request, int $election): View
    {
        $record = Election::query()
            ->with(['candidacies' => fn ($query) => $query->where('user_id', $request->user()->getKey())])
            ->findOrFail($election);

        if ((int) $record->organizer_id !== (int) $request->user()->getKey()) {
            abort_unless(in_array($record->status, $this->publishedStatuses(), true), 404);

            $candidacy = $record->candidacies->first();
            abort_unless($this->isPublic($record) || $candidacy !== null, 404);

            $now = CarbonImmutable::now();

            return view('elections.details', [
                'election' => $record,
                'eligibility' => EligibilityRules::fromStored($record->eligibility_rules),
                'candidacy' => $candidacy,
                'canApply' => $this->isPublic($record)
                    && $request->user()->isVerified()
                    && $candidacy === null
                    && $record->status === ElectionStatus::NOMINATION_OPEN->value
                    && ($record->nomination_start === null || $record->nomination_start->lte($now))
                    && ($record->nomination_end === null || $record->nomination_end->gte($now)),
            ]);
        }

        return $this->managementView($record, $request, false);
    }

    public function edit(Request $request, int $election): View|RedirectResponse
    {
        $record = $this->service->findOwned($request->user(), $election);
        if ($record->status !== ElectionStatus::DRAFT->value) {
            return redirect()->route('elections.show', $record)->with('error', 'Only draft elections can be edited.');
        }

        return view('elections.edit', [
            'election' => $record,
            'eligibility' => EligibilityRules::fromStored($record->eligibility_rules),
        ]);
    }

    public function update(UpdateElectionRequest $request, int $election): RedirectResponse
    {
        try {
            $record = $this->service->update($request->user(), $election, $request->validated());

            return redirect()->route('elections.show', $record)->with('success', 'Draft configuration updated.');
        } catch (ConcurrencyException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function importPrivateVoters(ImportPrivateVoterListRequest $request, int $election): RedirectResponse
    {
        try {
            $record = $this->service->findOwned($request->user(), $election);
            if (! $this->canManagePrivateRoster($record)) {
                return back()->with('error', 'Private voters can be managed while the election is a draft or nominations are open. The list locks when voting begins.');
            }
            if (! $this->isPrivate($record)) {
                return back()->with('error', 'Only private elections can receive a voter list.');
            }

            $rows = $this->privateVoterRosterFile->parse($request->file('voter_list'));
            $summary = $this->voterModule->importPrivateRoster(
                (int) $record->getKey(),
                (int) $request->user()->getKey(),
                $rows,
            );
            if ($summary === null) {
                return back()->with('error', 'The User/Voter service could not process the voter list. No import status was saved.');
            }

            $this->service->recordPrivateRosterImport($request->user(), (int) $record->getKey(), $summary);

            $message = sprintf(
                'Private voter list processed: %d new voter(s) assigned%s%s.',
                $summary['assigned'],
                $summary['skipped'] > 0 ? ', '.$summary['skipped'].' already assigned voter(s) skipped' : '',
                $summary['unmatchedCount'] > 0 ? ', '.$summary['unmatchedCount'].' row(s) did not match a registered user' : '',
            );

            return redirect()->route('elections.show', $record)->with('success', $message);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (BusinessRuleException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function downloadPrivateVoterTemplate(): StreamedResponse
    {
        return response()->streamDownload(static function (): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['full_name', 'ic_number']);
            fputcsv($output, ['Nur Aisyah Binti Ali', '050729-14-5232']);
            fputcsv($output, ['Aisha Rahman', 'E1234567']);
            fclose($output);
        }, 'votetrust-private-voter-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function privateVoterDirectory(Request $request, int $election): JsonResponse
    {
        $record = $this->service->findOwned($request->user(), $election);
        abort_unless($this->canManagePrivateRoster($record) && $this->isPrivate($record), 403);

        $directory = $this->voterModule->privateVoterDirectory(
            (int) $record->getKey(),
            (int) $request->user()->getKey(),
            (string) $request->query('search', ''),
            max(1, (int) $request->query('page', 1)),
        );
        if ($directory === null) {
            return response()->json([
                'message' => 'The User/Voter service could not load registered voters. Please try again.',
            ], 502);
        }

        return response()->json(['data' => $directory]);
    }

    public function updatePrivateVoterSelection(UpdatePrivateVoterSelectionRequest $request, int $election, int $user): JsonResponse
    {
        try {
            $record = $this->service->findOwned($request->user(), $election);
            abort_unless($this->canManagePrivateRoster($record) && $this->isPrivate($record), 403);

            $selection = $this->voterModule->setPrivateVoterSelection(
                (int) $record->getKey(),
                (int) $request->user()->getKey(),
                $user,
                (bool) $request->validated('assigned'),
            );
            if ($selection === null) {
                return response()->json([
                    'message' => 'The User/Voter service could not save this voter selection. Please try again.',
                ], 502);
            }
            if ($selection['reasonCode'] === 'USER_UNAVAILABLE') {
                return response()->json([
                    'message' => 'This user is no longer available for voter assignment.',
                ], 422);
            }

            $this->service->recordPrivateVoterSelection($request->user(), (int) $record->getKey(), $selection);

            return response()->json(['data' => $selection]);
        } catch (BusinessRuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reviewCandidates(ReviewCandidatesRequest $request, int $election): RedirectResponse
    {
        try {
            $updated = $this->service->reviewCandidates(
                $request->user(),
                $election,
                $request->validated('decisions'),
                $request->validated('current_password'),
                (int) $request->validated('version'),
            );

            return redirect()->route('elections.show', $election)->with(
                'success',
                $updated === 0 ? 'Candidate decisions are already up to date.' : "{$updated} candidate decision(s) saved.",
            );
        } catch (BusinessRuleException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function transition(TransitionElectionRequest $request, int $election): RedirectResponse
    {
        $data = $request->validated();
        try {
            $record = $this->service->transition(
                $request->user(),
                $election,
                (string) $data['action'],
                (string) $data['current_password'],
                $data,
            );

            return redirect()->route('elections.show', $record)
                ->with('success', 'Lifecycle updated to '.str($record->status)->replace('_', ' ')->lower()->headline().'.');
        } catch (BusinessRuleException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function managementView(Election $election, Request $request, bool $isAdminView): View
    {
        $summary = $this->service->candidateSummary($election);

        return view('elections.show', [
            'election' => $election,
            'availability' => $this->service->availability($election),
            'actions' => $this->service->actions($election, $request->user()),
            'candidateSummary' => $summary,
            'logs' => $this->service->transitionLogs($election),
            'isAdminView' => $isAdminView,
        ]);
    }

    /** @return list<string> */
    private function publishedStatuses(): array
    {
        return [
            ElectionStatus::NOMINATION_OPEN->value,
            ElectionStatus::VOTING_OPEN->value,
            ElectionStatus::CLOSED->value,
        ];
    }

    private function isPublic(Election $election): bool
    {
        return EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PUBLIC;
    }

    private function isPrivate(Election $election): bool
    {
        return EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PRIVATE;
    }

    private function canManagePrivateRoster(Election $election): bool
    {
        return in_array($election->status, [
            ElectionStatus::DRAFT->value,
            ElectionStatus::NOMINATION_OPEN->value,
        ], true);
    }
}
