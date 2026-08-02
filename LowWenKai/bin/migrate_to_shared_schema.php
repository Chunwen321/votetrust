<?php

declare(strict_types=1);

use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Core\Database;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Models\Organizer;
use VoteTrust\Election\Models\User;

$root = dirname(__DIR__);
require $root . '/autoload.php';
Config::load($root);
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));

if (!in_array('--confirm-drop-lwk-tables', $argv, true)) {
    throw new RuntimeException(
        "Refusing destructive migration without --confirm-drop-lwk-tables. Create and verify a database backup first.\n"
    );
}

$pdo = Database::connection();
$database = (string) Config::get('DB_DATABASE', 'election_db');

$tableExists = static function (string $table) use ($pdo, $database): bool {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $statement->execute([$database, $table]);

    return (int) $statement->fetchColumn() === 1;
};

$legacyTables = [
    'lwk_users',
    'lwk_elections',
    'lwk_payment_verifications',
    'lwk_election_transition_logs',
];
foreach ($legacyTables as $table) {
    if (!$tableExists($table)) {
        throw new RuntimeException("Expected legacy table is missing: {$table}");
    }
}

$schema = file_get_contents($root . '/database/schema.sql');
if (!is_string($schema)) {
    throw new RuntimeException('Unable to read database/schema.sql');
}
$statements = preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [];
foreach ($statements as $statement) {
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }
}

$legacyUser = $pdo->query('SELECT * FROM `lwk_users` ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!is_array($legacyUser)) {
    throw new RuntimeException('The legacy organiser account was not found.');
}

$organizer = User::where('username', 'LowWenKaiOrganizer')->first();
if (!$organizer instanceof User) {
    $organizer = User::create([
        'username' => 'LowWenKaiOrganizer',
        'password_hash' => (string) $legacyUser['password_hash'],
        'is_admin' => 0,
    ]);
}
if (!Organizer::find($organizer->id()) instanceof Organizer) {
    Organizer::create(['id' => $organizer->id()]);
}

$admin = User::where('username', 'SystemAdmin')->first();
if (!$admin instanceof User) {
    $admin = User::create([
        'username' => 'SystemAdmin',
        'password_hash' => password_hash('VoteTrustAdmin!2026', PASSWORD_DEFAULT),
        'is_admin' => 1,
    ]);
} elseif (!$admin->isAdmin()) {
    $admin->set('is_admin', 1);
    $admin->save();
}
if (Organizer::find($admin->id()) instanceof Organizer) {
    throw new RuntimeException('SystemAdmin must not also be an organiser.');
}

$legacyElections = $pdo->query('SELECT * FROM `lwk_elections` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$legacyCount = count($legacyElections);
$migratedIds = [];

$pdo->beginTransaction();
try {
    foreach ($legacyElections as $legacy) {
        $logsStatement = $pdo->prepare(
            'SELECT * FROM `lwk_election_transition_logs` WHERE election_id = ? ORDER BY created_at, id'
        );
        $logsStatement->execute([(int) $legacy['id']]);
        $legacyLogs = $logsStatement->fetchAll(PDO::FETCH_ASSOC);
        $history = [];
        foreach ($legacyLogs as $log) {
            $metadata = json_decode((string) $log['metadata_json'], true);
            $history[] = [
                'actor_user_id' => $organizer->id(),
                'request_id' => (string) $log['request_id'],
                'action' => (string) $log['action'],
                'from_state' => (string) $log['from_state'],
                'to_state' => (string) $log['to_state'],
                'metadata' => is_array($metadata) ? $metadata : [],
                'created_at' => (string) $log['created_at'],
            ];
        }

        $status = (string) $legacy['status'];
        $requiresApprovalCorrection = in_array($status, [
            Election::NOMINATION_OPEN,
            Election::VOTING_OPEN,
            Election::CLOSED,
        ], true) && $legacy['payment_verified_at'] !== null;
        if ($requiresApprovalCorrection) {
            $history[] = [
                'actor_user_id' => $admin->id(),
                'request_id' => 'MIGRATION-' . str_pad((string) $legacy['id'], 6, '0', STR_PAD_LEFT),
                'action' => 'enforce_admin_approval',
                'from_state' => $status,
                'to_state' => Election::PENDING_ADMIN_APPROVAL,
                'metadata' => ['reason' => 'Workflow corrected to require independent administrator approval.'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $status = Election::PENDING_ADMIN_APPROVAL;
        }

        $verificationStatement = $pdo->prepare(
            'SELECT * FROM `lwk_payment_verifications` WHERE election_id = ? ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $verificationStatement->execute([(int) $legacy['id']]);
        $verification = $verificationStatement->fetch(PDO::FETCH_ASSOC);

        $existingStatement = $pdo->prepare(
            'SELECT id FROM `election` WHERE organizer_id = ? AND title = ? AND created_at = ? LIMIT 1'
        );
        $existingStatement->execute([$organizer->id(), (string) $legacy['title'], (string) $legacy['created_at']]);
        $existingId = $existingStatement->fetchColumn();
        if ($existingId !== false) {
            $migratedIds[] = (int) $existingId;
            continue;
        }

        $insert = $pdo->prepare(<<<'SQL'
INSERT INTO `election` (
    organizer_id, title, description, eligibility_rules,
    nomination_start, nomination_end, voting_start, voting_end,
    hosting_fee, currency, status, payment_id, payment_reference,
    payment_verified_at, payment_request_id, payment_provider_status,
    payment_response_json, approved_by, approved_at, rejected_by,
    rejected_at, rejection_reason, transition_history_json, version,
    created_at, updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?,
    NULL, NULL, NULL, NULL, NULL, ?, ?, ?, ?
)
SQL);
        $insert->execute([
            $organizer->id(),
            (string) $legacy['title'],
            (string) $legacy['description'],
            (string) $legacy['eligibility_rules'],
            (string) $legacy['nomination_start'],
            (string) $legacy['nomination_end'],
            (string) $legacy['voting_start'],
            (string) $legacy['voting_end'],
            (string) $legacy['hosting_fee'],
            (string) $legacy['currency'],
            $status,
            $legacy['payment_reference'],
            $legacy['payment_verified_at'],
            is_array($verification) ? (string) $verification['request_id'] : null,
            is_array($verification) ? (string) $verification['provider_status'] : null,
            is_array($verification) ? (string) $verification['response_json'] : null,
            json_encode($history, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (int) $legacy['version'] + ($requiresApprovalCorrection ? 1 : 0),
            (string) $legacy['created_at'],
            date('Y-m-d H:i:s'),
        ]);
        $migratedIds[] = (int) $pdo->lastInsertId();
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

if (count($migratedIds) !== $legacyCount) {
    throw new RuntimeException('Election count verification failed; legacy tables were not dropped.');
}
foreach ($migratedIds as $id) {
    $election = Election::find($id);
    if (!$election instanceof Election || $election->transitionLogs() === []) {
        throw new RuntimeException("Transition history verification failed for shared election {$id}.");
    }
    if ($election->paymentVerified() && $election->paymentVerification() === null) {
        throw new RuntimeException("Payment verification migration failed for shared election {$id}.");
    }
}

// Child tables must be removed before their referenced parent tables.
$pdo->exec('DROP TABLE `lwk_election_transition_logs`');
$pdo->exec('DROP TABLE `lwk_payment_verifications`');
$pdo->exec('DROP TABLE `lwk_elections`');
$pdo->exec('DROP TABLE `lwk_users`');

foreach ($legacyTables as $table) {
    if ($tableExists($table)) {
        throw new RuntimeException("Legacy table still exists after migration: {$table}");
    }
}

echo "Shared-schema migration completed.\n";
echo "Migrated elections: {$legacyCount}\n";
echo "Organiser: LowWenKaiOrganizer / VoteTrust!2026\n";
echo "Administrator: SystemAdmin / VoteTrustAdmin!2026\n";
echo "Removed tables: " . implode(', ', $legacyTables) . "\n";

