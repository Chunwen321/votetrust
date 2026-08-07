<?php

declare(strict_types=1);

namespace VoteTrust\Election\Controllers;

use DateTimeImmutable;
use VoteTrust\Election\Core\Auth;
use VoteTrust\Election\Core\Csrf;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Core\Session;
use VoteTrust\Election\Core\View;
use VoteTrust\Election\Exceptions\HttpException;
use VoteTrust\Election\Exceptions\ValidationException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\ElectionService;

final class ElectionController
{
    private readonly ElectionService $service;

    public function __construct()
    {
        $this->service = ElectionService::fromConfig();
    }

    public function index(Request $request): Response
    {
        $user = Auth::requireUser();
        if (!$user->isOrganizer() || $user->isAdmin()) {
            return Response::redirect(url('/admin/elections'));
        }

        return Response::html(View::render('elections/index', [
            'user' => $user,
            'elections' => $user->elections(),
        ]));
    }

    public function create(Request $request): Response
    {
        $user = Auth::requireUser();
        if (!$user->isOrganizer() || $user->isAdmin()) {
            return Response::redirect(url('/admin/elections'));
        }
        $now = new DateTimeImmutable('now');

        return Response::html(View::render('elections/create', [
            'errors' => Session::consumeFlash('errors', []),
            'defaults' => [
                'nomination_start' => $now->modify('+1 day')->format('Y-m-d\\TH:i'),
                'nomination_end' => $now->modify('+8 days')->format('Y-m-d\\TH:i'),
                'voting_start' => $now->modify('+9 days')->format('Y-m-d\\TH:i'),
                'voting_end' => $now->modify('+10 days')->format('Y-m-d\\TH:i'),
                'hosting_fee' => '100.00',
                'currency' => 'MYR',
            ],
        ]));
    }

    public function store(Request $request): Response
    {
        $user = Auth::requireUser();
        Csrf::assertValid($request->input('_token'));

        try {
            $election = $this->service->create($user, $request->all());
        } catch (ValidationException $exception) {
            $this->preserveForm($request, $exception);

            return Response::redirect(url('/elections/create'));
        }

        Session::flash('success', 'Election draft created. Review it before submitting for payment.');

        return Response::redirect(url('/elections/' . $election->id()));
    }

    public function show(Request $request): Response
    {
        $user = Auth::requireUser();
        $election = $this->service->findOwned($user, (int) $request->route('id'));
        $candidateSummary = $this->service->candidateSummary($election);

        return Response::html(View::render('elections/show', [
            'election' => $election,
            'user' => $user,
            'availability' => $this->service->availability($election),
            'actions' => $this->service->actions($election, $user),
            'candidateSummary' => $candidateSummary,
            'logs' => $election->transitionLogs(),
            'isAdminView' => false,
            'backUrl' => url('/elections'),
            'transitionUrl' => url('/elections/' . $election->id() . '/transition'),
            'candidateReviewUrl' => url('/elections/' . $election->id() . '/candidates'),
        ]));
    }

    public function reviewCandidates(Request $request): Response
    {
        $user = Auth::requireUser();
        Csrf::assertValid($request->input('_token'));
        $id = (int) $request->route('id');
        $decisions = $request->input('decisions', []);

        try {
            $updated = $this->service->reviewCandidates(
                $user,
                $id,
                is_array($decisions) ? $decisions : [],
                (string) $request->input('current_password', '')
            );
            Session::flash(
                'success',
                $updated > 0
                    ? "{$updated} candidate decision" . ($updated === 1 ? '' : 's') . ' saved.'
                    : 'Candidate decisions are already up to date.'
            );
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/elections/' . $id));
    }

    public function edit(Request $request): Response
    {
        $user = Auth::requireUser();
        $election = $this->service->findOwned($user, (int) $request->route('id'));
        if ($election->status() !== Election::DRAFT) {
            Session::flash('error', 'Only draft elections can be edited.');

            return Response::redirect(url('/elections/' . $election->id()));
        }

        return Response::html(View::render('elections/edit', [
            'election' => $election,
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function update(Request $request): Response
    {
        $user = Auth::requireUser();
        Csrf::assertValid($request->input('_token'));
        $id = (int) $request->route('id');

        try {
            $election = $this->service->update($user, $id, $request->all());
        } catch (ValidationException $exception) {
            $this->preserveForm($request, $exception);

            return Response::redirect(url('/elections/' . $id . '/edit'));
        }

        Session::flash('success', 'Draft configuration updated.');

        return Response::redirect(url('/elections/' . $election->id()));
    }

    public function transition(Request $request): Response
    {
        $user = Auth::requireUser();
        Csrf::assertValid($request->input('_token'));
        $id = (int) $request->route('id');

        try {
            $election = $this->service->transition(
                $user,
                $id,
                trim((string) $request->input('action', '')),
                (string) $request->input('current_password', ''),
                [
                    'payment_reference' => $request->input('payment_reference'),
                    'reason' => $request->input('reason'),
                ]
            );
            Session::flash('success', 'Lifecycle updated to ' . ucwords(strtolower(str_replace('_', ' ', $election->status()))) . '.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/elections/' . $id));
    }

    private function preserveForm(Request $request, ValidationException $exception): void
    {
        $old = $request->all();
        unset($old['_token'], $old['current_password']);
        Session::put('_old_input', $old);
        Session::flash('errors', $exception->errors);
        Session::flash('error', $exception->getMessage());
    }
}
