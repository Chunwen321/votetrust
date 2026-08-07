<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Controllers\AdminController;
use VoteTrust\UserAdmin\Controllers\AuthController;
use VoteTrust\UserAdmin\Controllers\DashboardController;
use VoteTrust\UserAdmin\Controllers\ProfileController;
use VoteTrust\UserAdmin\Controllers\VoterController;
use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Response;
use VoteTrust\UserAdmin\Core\Router;

return static function (Router $router): void {
    $router->get('/', static function (Request $request): Response {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        return Response::redirect(url(Auth::isAdmin($user) ? '/admin' : '/dashboard'));
    });
    $router->get('/login', [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'registerForm']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/dashboard', [DashboardController::class, 'show']);
    $router->get('/profile', [ProfileController::class, 'show']);
    $router->post('/profile', [ProfileController::class, 'update']);
    $router->post('/profile/password', [ProfileController::class, 'updatePassword']);

    $router->get('/voter', [VoterController::class, 'dashboard']);
    $router->get('/elections', [VoterController::class, 'dashboard']);

    $router->get('/admin', [AdminController::class, 'dashboard']);
    $router->get('/admin/elections', [AdminController::class, 'elections']);
    $router->get('/admin/elections/create', [AdminController::class, 'createElection']);
    $router->post('/admin/elections', [AdminController::class, 'storeElection']);
    $router->get('/admin/elections/{id}', [AdminController::class, 'showElection']);
    $router->get('/admin/elections/{id}/edit', [AdminController::class, 'editElection']);
    $router->post('/admin/elections/{id}', [AdminController::class, 'updateElection']);
    $router->post('/admin/elections/{id}/action', [AdminController::class, 'electionAction']);
    $router->post('/admin/elections/{id}/voters', [AdminController::class, 'addVoter']);
    $router->post('/admin/elections/{id}/voters/{userId}', [AdminController::class, 'voterAction']);
    $router->post('/admin/elections/{id}/candidates', [AdminController::class, 'addCandidate']);
    $router->post('/admin/elections/{id}/candidates/{userId}', [AdminController::class, 'candidateAction']);
};
