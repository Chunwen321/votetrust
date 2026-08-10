<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConcurrencyException;
use App\Http\Requests\ReviewCandidatesRequest;
use App\Http\Requests\StoreElectionRequest;
use App\Http\Requests\TransitionElectionRequest;
use App\Http\Requests\UpdateElectionRequest;
use App\Models\Election;
use App\Services\ElectionService;
use App\Services\User\VotingParticipationClient;
use App\Services\VoterService;
use App\States\ElectionStatus;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ElectionController extends Controller
{
    public function __construct(
        private readonly ElectionService $service,
        private readonly VoterService $voters,
        private readonly VotingParticipationClient $voting,
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

        return view('elections.index', [
            'user' => $user,
            'voterEnrollments' => $enrollments,
            'organizedElections' => Election::query()
                ->where('organizer_id', $user->getKey())
                ->latest('updated_at')
                ->get(),
            'candidateElections' => $candidateElections,
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
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]]);
    }

    public function store(StoreElectionRequest $request): RedirectResponse
    {
        $election = $this->service->create($request->user(), $request->validated());

        return redirect()->route('elections.show', $election)
            ->with('success', 'Election draft created. Review it before submitting for payment.');
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
}
