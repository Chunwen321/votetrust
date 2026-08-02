<?php

declare(strict_types=1);

use VoteTrust\Election\Controllers\Api\ElectionAvailabilityController;
use VoteTrust\Election\Controllers\Api\AdminElectionApprovalController;
use VoteTrust\Election\Core\Router;

return static function (Router $router): void {
    $router->get('/api/v1/elections/{id}/availability', [ElectionAvailabilityController::class, 'show']);
    $router->post('/api/v1/elections/{id}/approval', [AdminElectionApprovalController::class, 'decide']);
};
