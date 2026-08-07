<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Controllers;

use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Csrf;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Response;
use VoteTrust\UserAdmin\Core\Session;
use VoteTrust\UserAdmin\Core\View;
use VoteTrust\UserAdmin\Exceptions\AuthorizationException;
use VoteTrust\UserAdmin\Exceptions\HttpException;
use VoteTrust\UserAdmin\Exceptions\ValidationException;
use VoteTrust\UserAdmin\Services\AdminManagementService;

final class AdminController
{
    public function dashboard(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $management = new AdminManagementService();
        $elections = $management->elections();

        return Response::html(View::render('admin/dashboard', [
            'pageTitle' => 'Admin dashboard',
            'user' => $admin,
            'counts' => $management->dashboardCounts(),
            'recentElections' => array_slice($elections, 0, 5),
        ]));
    }

    public function elections(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $management = new AdminManagementService();

        return Response::html(View::render('admin/elections/index', [
            'pageTitle' => 'Election management',
            'user' => $admin,
            'elections' => $management->elections(),
        ]));
    }

    public function createElection(Request $request): Response
    {
        return Response::html(View::render('admin/elections/form', [
            'pageTitle' => 'Create election',
            'user' => $this->requireAdmin(),
            'election' => null,
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function storeElection(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $admin = $this->requireAdmin();
        $input = $this->electionInput($request);
        try {
            $electionId = (new AdminManagementService())->createElection($admin, $input);
            Session::flash('success', 'Election created as a draft. Configure voters and candidates before approving it.');

            return Response::redirect(url('/admin/elections/' . $electionId));
        } catch (HttpException $exception) {
            Session::flash('errors', $exception->errors);
            Session::put('_old_input', $input);

            return Response::redirect(url('/admin/elections/create'));
        }
    }

    public function showElection(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $management = new AdminManagementService();
        $electionId = $this->routeId($request, 'id');

        return Response::html(View::render('admin/elections/show', [
            'pageTitle' => 'Election control centre',
            'user' => $admin,
            'election' => $management->election($electionId),
            'voters' => $management->voters($electionId),
            'candidates' => $management->candidates($electionId),
            'eligibleUsers' => $management->eligibleUsers(),
        ]));
    }

    public function editElection(Request $request): Response
    {
        $admin = $this->requireAdmin();
        $election = (new AdminManagementService())->election($this->routeId($request, 'id'));

        return Response::html(View::render('admin/elections/form', [
            'pageTitle' => 'Edit election',
            'user' => $admin,
            'election' => $election,
            'errors' => Session::consumeFlash('errors', []),
        ]));
    }

    public function updateElection(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $admin = $this->requireAdmin();
        $electionId = $this->routeId($request, 'id');
        $input = $this->electionInput($request);
        try {
            (new AdminManagementService())->updateElection($admin, $electionId, $input);
            Session::flash('success', 'Election details have been updated.');
        } catch (HttpException $exception) {
            Session::flash('errors', $exception->errors);
            Session::put('_old_input', $input);

            return Response::redirect(url('/admin/elections/' . $electionId . '/edit'));
        }

        return Response::redirect(url('/admin/elections/' . $electionId));
    }

    public function electionAction(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $admin = $this->requireAdmin();
        $electionId = $this->routeId($request, 'id');
        try {
            (new AdminManagementService())->electionAction($admin, $electionId, (string) $request->input('action'));
            Session::flash('success', 'Election lifecycle has been updated.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/admin/elections/' . $electionId));
    }

    public function addVoter(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $admin = $this->requireAdmin();
        $electionId = $this->routeId($request, 'id');
        try {
            (new AdminManagementService())->addVoter(
                $admin,
                $electionId,
                $this->positiveId($request->input('user_id'), 'user'),
                (string) $request->input('status', 'APPROVED')
            );
            Session::flash('success', 'Voter enrolment saved.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/admin/elections/' . $electionId . '#voters'));
    }

    public function voterAction(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $this->requireAdmin();
        $electionId = $this->routeId($request, 'id');
        try {
            (new AdminManagementService())->voterAction(
                $electionId,
                $this->routeId($request, 'userId'),
                (string) $request->input('status')
            );
            Session::flash('success', 'Voter status updated.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/admin/elections/' . $electionId . '#voters'));
    }

    public function addCandidate(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $this->requireAdmin();
        $electionId = $this->routeId($request, 'id');
        try {
            (new AdminManagementService())->addCandidate(
                $electionId,
                $this->positiveId($request->input('user_id'), 'user'),
                (string) $request->input('status', 'Approved')
            );
            Session::flash('success', 'Candidate entry saved.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/admin/elections/' . $electionId . '#candidates'));
    }

    public function candidateAction(Request $request): Response
    {
        Csrf::assertValid($request->input('_token'));
        $this->requireAdmin();
        $electionId = $this->routeId($request, 'id');
        try {
            (new AdminManagementService())->candidateAction(
                $electionId,
                $this->routeId($request, 'userId'),
                (string) $request->input('status')
            );
            Session::flash('success', 'Candidate status updated.');
        } catch (HttpException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect(url('/admin/elections/' . $electionId . '#candidates'));
    }

    /** @return array<string, string> */
    private function electionInput(Request $request): array
    {
        return [
            'title' => trim((string) $request->input('title', '')),
            'description' => trim((string) $request->input('description', '')),
            'eligibility_rules' => trim((string) $request->input('eligibility_rules', '')),
            'nomination_start' => trim((string) $request->input('nomination_start', '')),
            'nomination_end' => trim((string) $request->input('nomination_end', '')),
            'voting_start' => trim((string) $request->input('voting_start', '')),
            'voting_end' => trim((string) $request->input('voting_end', '')),
            'hosting_fee' => trim((string) $request->input('hosting_fee', '')),
            'currency' => trim((string) $request->input('currency', 'MYR')),
        ];
    }

    private function routeId(Request $request, string $key): int
    {
        return $this->positiveId($request->route($key), $key);
    }

    private function positiveId(mixed $value, string $name): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new ValidationException(errors: [$name => "Choose a valid {$name}."]);
        }

        return (int) $id;
    }

    /** @return array<string, mixed> */
    private function requireAdmin(): array
    {
        $user = Auth::requireUser();
        if (!Auth::isAdmin($user)) {
            throw new AuthorizationException('The administrative workspace is restricted to system administrators.');
        }

        return $user;
    }
}
