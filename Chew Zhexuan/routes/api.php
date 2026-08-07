<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Controllers\Api\VoterEligibilityController;
use VoteTrust\UserAdmin\Core\Router;

return static function (Router $router): void {
    $router->get('/api/v1/voters/eligibility', [VoterEligibilityController::class, 'show']);
};
