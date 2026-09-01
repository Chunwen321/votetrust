<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\TransitionElectionRequest;
use App\Models\Election;
use App\Services\ElectionService;
use App\States\ElectionStatus;
use App\Support\PublicElectionExpiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminElectionController extends Controller
{
    public function __construct(private readonly ElectionService $service) {}

    public function index(Request $request): View
    {
        if (! $this->service->isAdmin($request->user())) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ElectionStatus::class)],
            'payment' => ['nullable', Rule::in(['verified', 'unverified'])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $payment = $filters['payment'] ?? null;

        $electionsQuery = Election::query()
            ->with('organizer')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('organizer', fn ($organizer) => $organizer
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));

                    if (ctype_digit($search)) {
                        $query->orWhere('election.id', (int) $search);
                    }
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($payment === 'verified', fn ($query) => $query->whereNotNull('payment_verified_at'))
            ->when($payment === 'unverified', fn ($query) => $query->whereNull('payment_verified_at'));

        $matchingPendingApprovalCount = (clone $electionsQuery)
            ->where('status', Election::PENDING_ADMIN_APPROVAL)
            ->count();

        return view('admin.elections.index', [
            'elections' => $electionsQuery
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Election::PENDING_ADMIN_APPROVAL])
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
            'pendingApprovalCount' => Election::query()
                ->where('status', Election::PENDING_ADMIN_APPROVAL)
                ->count(),
            'matchingPendingApprovalCount' => $matchingPendingApprovalCount,
            'statusOptions' => ElectionStatus::cases(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment' => $payment,
            ],
        ]);
    }

    public function show(Request $request, int $election): View
    {
        $record = $this->service->findForAdmin($request->user(), $election);
        $record = $this->service->expireOverduePublicElection(
            $request->user(),
            (int) $record->getKey(),
            'administrator_view',
        ) ?? $record;

        return view('elections.show', [
            'election' => $record,
            'availability' => $this->service->availability($record),
            'actions' => $this->service->actions($record, $request->user()),
            'candidateSummary' => $this->service->candidateSummary($record),
            'canManageParticipants' => false,
            'logs' => $this->service->transitionLogs($record),
            'isAdminView' => true,
        ]);
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

            if ($record->status === ElectionStatus::EXPIRED->value) {
                return redirect()->route('admin.elections.show', $record)
                    ->with('error', PublicElectionExpiry::MESSAGE);
            }

            return redirect()->route('admin.elections.show', $record)
                ->with('success', 'Administrator decision recorded.');
        } catch (BusinessRuleException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
