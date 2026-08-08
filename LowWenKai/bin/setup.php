<?php

declare(strict_types=1);

use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Core\Database;
use VoteTrust\Election\Models\Candidate;
use VoteTrust\Election\Models\Candidacy;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Models\Organizer;
use VoteTrust\Election\Models\User;
use VoteTrust\Election\Services\ElectionService;

$root = dirname(__DIR__);
require $root . '/autoload.php';
Config::load($root);
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));

$schema = file_get_contents($root . '/database/schema.sql');
if (!is_string($schema)) {
    throw new RuntimeException('Unable to read database/schema.sql');
}

$pdo = Database::connection();
$statements = preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [];
foreach ($statements as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}

$organizerUsername = 'LowWenKaiOrganizer';
$organizerPassword = 'VoteTrust!2026';
$organizer = User::where('username', $organizerUsername)->first();
if (!$organizer instanceof User) {
    $organizer = User::create([
        'username' => $organizerUsername,
        'password_hash' => password_hash($organizerPassword, PASSWORD_DEFAULT),
        'is_admin' => 0,
    ]);
}
if (!Organizer::find($organizer->id()) instanceof Organizer) {
    Organizer::create(['id' => $organizer->id()]);
}

$adminUsername = 'SystemAdmin';
$adminPassword = 'VoteTrustAdmin!2026';
$admin = User::where('username', $adminUsername)->first();
if (!$admin instanceof User) {
    $admin = User::create([
        'username' => $adminUsername,
        'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
        'is_admin' => 1,
    ]);
} elseif (!$admin->isAdmin()) {
    $admin->set('is_admin', 1);
    $admin->save();
}

$organizerElections = Election::where('organizer_id', $organizer->id())->orderBy('created_at', 'DESC')->get();
$demoElection = null;
foreach ($organizerElections as $organizerElection) {
    if (!in_array($organizerElection->status(), [Election::CANCELLED, Election::REJECTED, Election::CLOSED], true)) {
        $demoElection = $organizerElection;
        break;
    }
}

if (!$demoElection instanceof Election) {
    $now = new DateTimeImmutable('now');
    $demoElection = ElectionService::fromConfig()->create($organizer, [
        'title' => 'VoteTrust Student Council Election 2026',
        'description' => 'A transparent institutional election used to demonstrate payment verification and independent administrator approval.',
        'eligibility_mode' => 'RESTRICTED',
        'eligibility_minimum_age' => '18',
        'eligibility_occupation' => 'STUDENT',
        'eligibility_nationality' => 'ANY',
        'nomination_start' => $now->modify('-2 days')->format('Y-m-d\\TH:i'),
        'nomination_end' => $now->modify('-1 hour')->format('Y-m-d\\TH:i'),
        'voting_start' => $now->modify('-30 minutes')->format('Y-m-d\\TH:i'),
        'voting_end' => $now->modify('+2 days')->format('Y-m-d\\TH:i'),
        'hosting_fee' => '100.00',
        'currency' => 'MYR',
    ]);
}

foreach (['CandidateAisha', 'CandidateDaniel'] as $candidateUsername) {
    $candidateUser = User::where('username', $candidateUsername)->first();
    if (!$candidateUser instanceof User) {
        $candidateUser = User::create([
            'username' => $candidateUsername,
            'password_hash' => password_hash('Candidate!2026', PASSWORD_DEFAULT),
            'is_admin' => 0,
        ]);
    }
    if (!Candidate::find($candidateUser->id()) instanceof Candidate) {
        Candidate::create(['id' => $candidateUser->id()]);
    }
    $existingApplication = Candidacy::where('candidate_id', $candidateUser->id())
        ->where('election_id', $demoElection->id())
        ->first();
    if (!$existingApplication instanceof Candidacy) {
        Candidacy::create([
            'candidate_id' => $candidateUser->id(),
            'election_id' => $demoElection->id(),
            'status' => Candidacy::PENDING,
        ]);
    }
}

$paymentDataDirectory = $root . '/payment_stub/data';
if (!is_dir($paymentDataDirectory)) {
    mkdir($paymentDataDirectory, 0700, true);
}

$payments = [
    'VT-HOST-PAID-001' => [
        'paymentStatus' => 'CONFIRMED',
        'amount' => 100.00,
        'currency' => 'MYR',
        'payerId' => $organizer->id(),
        'purpose' => 'ELECTION_HOSTING',
        'paymentId' => null,
    ],
    'VT-HOST-PENDING-001' => [
        'paymentStatus' => 'PENDING',
        'amount' => 100.00,
        'currency' => 'MYR',
        'payerId' => $organizer->id(),
        'purpose' => 'ELECTION_HOSTING',
        'paymentId' => null,
    ],
];
file_put_contents(
    $paymentDataDirectory . '/payments.json',
    json_encode($payments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);

echo "VoteTrust Election Module setup completed.\n";
echo 'Database: ' . Config::get('DB_DATABASE', 'election_db') . "\n";
echo "Organiser: {$organizerUsername} / {$organizerPassword}\n";
echo "Administrator: {$adminUsername} / {$adminPassword}\n";
echo "Demo candidate applications: CandidateAisha and CandidateDaniel\n";
echo "Confirmed demo payment: VT-HOST-PAID-001\n";

