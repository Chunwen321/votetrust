<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Controllers;

use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Response;
use VoteTrust\UserAdmin\Core\View;
use VoteTrust\UserAdmin\Services\VoterService;

final class DashboardController
{
    public function show(Request $request): Response
    {
        $user = Auth::requireUser();
        if (Auth::isAdmin($user)) {
            return Response::redirect(url('/admin'));
        }
        $enrolments = (new VoterService())->applicationsForVoter((int) $user['id']);
        $counts = ['APPROVED' => 0, 'PENDING' => 0, 'REJECTED' => 0, 'SUSPENDED' => 0];
        foreach ($enrolments as $enrolment) {
            $status = (string) ($enrolment['eligibility_status'] ?? 'PENDING');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        return Response::html(View::render('dashboard', [
            'pageTitle' => 'Dashboard',
            'user' => $user,
            'enrolments' => $enrolments,
            'counts' => $counts,
        ]));
    }
}
