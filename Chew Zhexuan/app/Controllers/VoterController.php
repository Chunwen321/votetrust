<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Controllers;

use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Response;
use VoteTrust\UserAdmin\Core\View;
use VoteTrust\UserAdmin\Services\VoterService;

final class VoterController
{
    public function dashboard(Request $request): Response
    {
        $user = Auth::requireUser();
        if (Auth::isAdmin($user)) {
            return Response::redirect(url('/admin'));
        }
        $voters = new VoterService();

        return Response::html(View::render('voter/dashboard', [
            'pageTitle' => 'My elections',
            'user' => $user,
            'applications' => $voters->applicationsForVoter((int) $user['id']),
        ]));
    }

}
