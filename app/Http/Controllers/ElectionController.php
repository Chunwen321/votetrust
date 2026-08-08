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
use App\States\ElectionStatus;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ElectionController extends Controller
{
    public function __construct(private readonly ElectionService $service) {}

    public function index(Request $request): View
    {
        $organizer = $this->service->requireOrganizer($request->user());

        return view('elections.index', [
            'elections' => Election::query()->where('organizer_id', $organizer->getKey())->latest('updated_at')->paginate(12),
        ]);
    }

    public function create(Request $request): View
    {
        $this->service->requireOrganizer($request->user());
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
        $record = $this->service->findOwned($request->user(), $election);

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
}
