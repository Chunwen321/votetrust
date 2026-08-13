<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Http\Requests\TransitionElectionRequest;
use App\Models\Election;
use App\Services\ElectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminElectionController extends Controller
{
    public function __construct(private readonly ElectionService $service) {}

    public function index(Request $request): View
    {
        if (! $this->service->isAdmin($request->user())) {
            abort(403);
        }

        return view('admin.elections.index', [
            'elections' => Election::query()
                ->with('organizer')
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Election::PENDING_ADMIN_APPROVAL])
                ->latest('updated_at')
                ->paginate(20),
            'pendingApprovalCount' => Election::query()
                ->where('status', Election::PENDING_ADMIN_APPROVAL)
                ->count(),
        ]);
    }

    public function show(Request $request, int $election): View
    {
        $record = $this->service->findForAdmin($request->user(), $election);

        return view('elections.show', [
            'election' => $record,
            'availability' => $this->service->availability($record),
            'actions' => $this->service->actions($record, $request->user()),
            'candidateSummary' => $this->service->candidateSummary($record),
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

            return redirect()->route('admin.elections.show', $record)
                ->with('success', 'Administrator decision recorded.');
        } catch (BusinessRuleException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
