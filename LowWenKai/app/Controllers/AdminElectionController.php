<?php

declare(strict_types=1);

namespace VoteTrust\Election\Controllers;

use VoteTrust\Election\Core\Auth;
use VoteTrust\Election\Core\Csrf;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Core\Session;
use VoteTrust\Election\Core\View;
use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Exceptions\HttpException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\ElectionService;

final class AdminElectionController
{
    private readonly ElectionService $service;

    public function __construct()
    {
        $this->service = ElectionService::fromConfig();
    }

    public function index(Request $request): Response
    {
        $admin = $this->requireAdmin();

        return Response::html(View::render('admin/elections', [
            'user' => $admin,
            'elections' => Election::query()->orderBy('updated_at', 'DESC')->get(),
        ]));
    }

    public function show(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $election = $this->service->findForAdmin($admin, (int) $request->route('id'));
        $candidateSummary = $this->service->candidateSummary($election);

        return Response::html(View::render('elections/show', [
            'election' => $election,
            'user' => $admin,
            'availability' => $this->service->availability($election),
            'actions' => $this->service->actions($election, $admin),
            'candidateSummary' => $candidateSummary,
            'logs' => $election->transitionLogs(),
            'isAdminView' => true,
            'backUrl' => url('/admin/elections'),
            'transitionUrl' => url('/admin/elections/' . $election->id() . '/transition'),
            'candidateReviewUrl' => null,
        ]));
    }

    public function transition(Request $request): Response
    {
        $admin = $this->requireAdmin();
        Csrf::assertValid($request->input('_token'));
        $id = (int) $request->route('id');

        try {
            $election = $this->service->transition(
                $admin,
                $id,
                trim((string) $request->input('action', '')),
                (string) $request->input('current_password', ''),
                ['reason' => $request->input('reason')]
            );
            Session::flash('success', 'Administrator decision recorded. Lifecycle is now ' . ucwords(strtolower(str_replace('_', ' ', $election->status()))) . '.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/admin/elections/' . $id));
    }

    private function requireAdmin(): \VoteTrust\Election\Models\User
    {
        $user = Auth::requireUser();
        if (!$user->isAdmin()) {
            throw new AuthorizationException('The administrator approval workspace is restricted to system administrators.');
        }

        return $user;
    }
}
