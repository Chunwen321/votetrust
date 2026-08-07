<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(class_exists(\VoteTrust\UserAdmin\Controllers\AuthController::class), 'Auth controller is not autoloadable.');
$assert(class_exists(\VoteTrust\UserAdmin\Controllers\VoterController::class), 'Voter controller is not autoloadable.');
$assert(class_exists(\VoteTrust\UserAdmin\Controllers\AdminController::class), 'Admin controller is not autoloadable.');
$assert(class_exists(\VoteTrust\UserAdmin\Controllers\DashboardController::class), 'Dashboard controller is not autoloadable.');
$assert(class_exists(\VoteTrust\UserAdmin\Services\AdminManagementService::class), 'Admin management service is not autoloadable.');
$assert(class_exists(\VoteTrust\UserAdmin\Controllers\Api\VoterEligibilityController::class), 'Eligibility API controller is not autoloadable.');
$assert(interface_exists(\VoteTrust\UserAdmin\Security\Authentication\LoginProtectionStrategy::class), 'Login protection strategy is not autoloadable.');
$assert(is_subclass_of(
    \VoteTrust\UserAdmin\Security\Authentication\DisableAccountAfterFailedAttemptsStrategy::class,
    \VoteTrust\UserAdmin\Security\Authentication\LoginProtectionStrategy::class
), 'Failed-login protection must use the login protection strategy interface.');
$assert(interface_exists(\VoteTrust\UserAdmin\Security\Session\SessionTerminationStrategy::class), 'Session termination strategy is not autoloadable.');
$assert(is_subclass_of(
    \VoteTrust\UserAdmin\Security\Session\FullSessionTerminationStrategy::class,
    \VoteTrust\UserAdmin\Security\Session\SessionTerminationStrategy::class
), 'Logout must use the session termination strategy interface.');

$schema = file_get_contents($root . '/database/schema.sql') ?: '';
foreach (['full_name', 'ic_number', 'email', 'account_status', 'failed_login_attempts', 'account_disabled_at', 'user_id', 'election_id', 'status', 'added_by'] as $requiredField) {
    $assert(str_contains($schema, '`' . $requiredField . '`'), "Schema is missing {$requiredField}.");
}

$routes = file_get_contents($root . '/routes/api.php') ?: '';
$assert(str_contains($routes, '/api/v1/voters/eligibility'), 'Eligibility API route is missing.');

$webRoutes = file_get_contents($root . '/routes/web.php') ?: '';
$assert(str_contains($webRoutes, "'/dashboard'"), 'User dashboard route is missing.');
$assert(str_contains($webRoutes, "'/elections'"), 'Election navigation route is missing.');
$assert(str_contains($webRoutes, "'/admin/elections'"), 'Admin election-management route is missing.');
$assert(!str_contains($webRoutes, '/voter/application'), 'Self-service voter enrolment routes must not be exposed.');
$users = file_get_contents($root . '/app/Models/UserRepository.php') ?: '';
$assert(str_contains($users, 'createUser('), 'Registration must create a standard user.');
$assert(!str_contains($users, 'createVoter('), 'Registration must not create a voter role.');

$auth = file_get_contents($root . '/app/Core/Auth.php') ?: '';
$assert(str_contains($auth, 'LoginProtectionStrategyFactory::make()'), 'Auth must use the login-protection strategy.');
$assert(str_contains($auth, 'SessionTerminationStrategyFactory::make()'), 'Auth logout must use the session-termination strategy.');

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "All User, Voter, and Admin module structural checks passed.\n";
