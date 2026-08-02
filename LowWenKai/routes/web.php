<?php

declare(strict_types=1);

use VoteTrust\Election\Controllers\AuthController;
use VoteTrust\Election\Controllers\AdminElectionController;
use VoteTrust\Election\Controllers\ElectionController;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Core\Router;

return static function (Router $router): void {
    $router->get('/', static fn (Request $request): Response => Response::redirect(url('/elections')));
    $router->get('/login', [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);

    $router->get('/elections', [ElectionController::class, 'index']);
    $router->get('/elections/create', [ElectionController::class, 'create']);
    $router->post('/elections', [ElectionController::class, 'store']);
    $router->get('/elections/{id}', [ElectionController::class, 'show']);
    $router->get('/elections/{id}/edit', [ElectionController::class, 'edit']);
    $router->put('/elections/{id}', [ElectionController::class, 'update']);
    $router->post('/elections/{id}/transition', [ElectionController::class, 'transition']);

    $router->get('/admin/elections', [AdminElectionController::class, 'index']);
    $router->get('/admin/elections/{id}', [AdminElectionController::class, 'show']);
    $router->post('/admin/elections/{id}/transition', [AdminElectionController::class, 'transition']);
};
