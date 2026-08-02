<?php

declare(strict_types=1);

use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Core\Database;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Models\Organizer;
use VoteTrust\Election\Models\User;

$root = dirname(__DIR__);
require_once $root . '/autoload.php';
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/Fakes/FakePaymentGateway.php';
require_once __DIR__ . '/Security/InsecureElectionWorkflow.php';
Config::load($root);
date_default_timezone_set('Asia/Kuala_Lumpur');
putenv('SECURITY_LOG_PATH=' . $root . '/tmp/test-security.log');

function resetTestDatabase(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    Database::setConnection($pdo);
    $pdo->exec(<<<'SQL'
CREATE TABLE `user` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    is_admin INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE organizer (
    id INTEGER PRIMARY KEY
);
CREATE TABLE election (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organizer_id INTEGER NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    eligibility_rules TEXT NULL,
    nomination_start TEXT NULL,
    nomination_end TEXT NULL,
    voting_start TEXT NULL,
    voting_end TEXT NULL,
    hosting_fee REAL NULL,
    currency TEXT NOT NULL DEFAULT 'MYR',
    status TEXT NOT NULL DEFAULT 'DRAFT',
    payment_id INTEGER NULL,
    payment_reference TEXT NULL,
    payment_verified_at TEXT NULL,
    payment_request_id TEXT NULL,
    payment_provider_status TEXT NULL,
    payment_response_json TEXT NULL,
    approved_by INTEGER NULL,
    approved_at TEXT NULL,
    rejected_by INTEGER NULL,
    rejected_at TEXT NULL,
    rejection_reason TEXT NULL,
    transition_history_json TEXT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NULL,
    updated_at TEXT NULL
);
SQL);
}

function makeTestUser(
    string $username = 'TestOrganizer',
    string $password = 'Correct!Pass1',
    bool $admin = false,
    bool $organizer = true
): User {
    $user = User::create([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_admin' => $admin ? 1 : 0,
    ]);
    if ($organizer) {
        Organizer::create(['id' => $user->id()]);
    }

    return $user;
}

function makeTestAdmin(string $password = 'Admin!Pass1'): User
{
    return makeTestUser('SystemAdmin', $password, true, false);
}

function makeTestElection(User $user, ?DateTimeImmutable $now = null): Election
{
    $now ??= new DateTimeImmutable('2026-08-01 12:00:00');

    return Election::create([
        'organizer_id' => $user->id(),
        'title' => 'Test Council Election',
        'description' => 'A sufficiently detailed election description for automated testing.',
        'eligibility_rules' => 'Verified active members only.',
        'nomination_start' => $now->modify('-2 days')->format('Y-m-d H:i:s'),
        'nomination_end' => $now->modify('-1 hour')->format('Y-m-d H:i:s'),
        'voting_start' => $now->modify('-30 minutes')->format('Y-m-d H:i:s'),
        'voting_end' => $now->modify('+1 hour')->format('Y-m-d H:i:s'),
        'hosting_fee' => '100.00',
        'currency' => 'MYR',
        'status' => Election::DRAFT,
        'transition_history_json' => '[]',
        'version' => 1,
    ]);
}
