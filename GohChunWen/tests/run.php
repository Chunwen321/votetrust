<?php

declare(strict_types=1);

use VoteTrust\Voting\Model\VoteAlreadyExists;
use VoteTrust\Voting\Model\VoteORM;
use VoteTrust\Voting\Service\AuthorizationDecorator;
use VoteTrust\Voting\Service\BasicVoteService;
use VoteTrust\Voting\Service\DuplicateVoteDecorator;
use VoteTrust\Voting\Service\VoteAccessDenied;
use VoteTrust\Voting\Service\VotingIntegration;

require dirname(__DIR__) . '/autoload.php';

final class FakeIntegration implements VotingIntegration
{
    public bool $eligible = true;
    public string $status = 'VOTING_OPEN';
    public bool $votingAllowed = true;

    public function openElections(string $requestId, string $timestamp): array
    {
        return [];
    }

    public function election(int $electionId, string $requestId, string $timestamp): array
    {
        return [
            'id' => $electionId,
            'title' => 'Test Election',
            'description' => 'Test ballot',
            'eligibilityRules' => 'Registered voters',
            'status' => $this->status,
            'votingAllowed' => $this->votingAllowed,
            'votingPeriod' => [],
            'candidacies' => [['id' => 31, 'candidateId' => 7, 'name' => 'Candidate Seven']],
        ];
    }

    public function voterIsEligible(int $voterId, int $electionId, string $requestId, string $timestamp): bool
    {
        return $this->eligible;
    }
}

function database(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE vote (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            election_id INTEGER NOT NULL,
            voter_id INTEGER NOT NULL,
            candidate_id INTEGER NOT NULL,
            cast_at TEXT NOT NULL,
            UNIQUE (voter_id, election_id)
        )'
    );

    return $pdo;
}

function request(array $overrides = []): array
{
    return array_merge([
        'voterId' => 2,
        'electionId' => 10,
        'candidateId' => 7,
        'requestID' => 'VOTE-TEST-0001',
        'timestamp' => date(DATE_ATOM),
    ], $overrides);
}

function assertTrue(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

function assertThrows(string $class, callable $work): void
{
    try {
        $work();
    } catch (Throwable $error) {
        if ($error instanceof $class) {
            return;
        }
        throw new RuntimeException('Expected ' . $class . ', received ' . $error::class . '.');
    }
    throw new RuntimeException("Expected {$class} was not thrown.");
}

$tests = [];
$tests['Records shared-schema candidate_id and auto-increment receipt'] = static function (): void {
    $pdo = database();
    $votes = new VoteORM($pdo);
    $integration = new FakeIntegration();
    $service = new AuthorizationDecorator(
        new DuplicateVoteDecorator(new BasicVoteService($votes), $votes),
        $integration
    );

    $vote = $service->cast(request());
    $row = $pdo->query('SELECT * FROM vote')->fetch(PDO::FETCH_ASSOC);
    assertTrue($vote->id === 1, 'Expected an auto-increment receipt ID.');
    assertTrue((int) $row['candidate_id'] === 7, 'Expected candidate_id 7 in the shared vote table.');
    assertTrue((int) $row['voter_id'] === 2 && (int) $row['election_id'] === 10, 'Vote ownership is incorrect.');
};

$tests['Rejects a duplicate vote for the same voter and election'] = static function (): void {
    $pdo = database();
    $votes = new VoteORM($pdo);
    $integration = new FakeIntegration();
    $service = new AuthorizationDecorator(
        new DuplicateVoteDecorator(new BasicVoteService($votes), $votes),
        $integration
    );
    $service->cast(request());
    assertThrows(VoteAlreadyExists::class, static fn () => $service->cast(request()));
};

$tests['Rejects ineligible voters and candidates outside the approved ballot'] = static function (): void {
    $pdo = database();
    $votes = new VoteORM($pdo);
    $integration = new FakeIntegration();
    $service = new AuthorizationDecorator(new BasicVoteService($votes), $integration);

    $integration->eligible = false;
    assertThrows(VoteAccessDenied::class, static fn () => $service->cast(request()));
    $integration->eligible = true;
    assertThrows(VoteAccessDenied::class, static fn () => $service->cast(request(['candidateId' => 99])));
};

$tests['Rejects voting when Election Module lifecycle is not open'] = static function (): void {
    $pdo = database();
    $votes = new VoteORM($pdo);
    $integration = new FakeIntegration();
    $integration->status = 'CLOSED';
    $integration->votingAllowed = false;
    $service = new AuthorizationDecorator(new BasicVoteService($votes), $integration);
    assertThrows(RuntimeException::class, static fn () => $service->cast(request()));
};

$tests['Integrated SQL contains every shared table, demo voters and vote seeds'] = static function (): void {
    $sql = file_get_contents(dirname(__DIR__) . '/election_db.sql');
    assertTrue(is_string($sql) && $sql !== '', 'Integrated SQL file could not be read.');

    foreach (['user', 'organizer', 'candidate', 'voter', 'payment', 'election', 'candidacy', 'vote'] as $table) {
        assertTrue(
            str_contains($sql, "CREATE TABLE IF NOT EXISTS `{$table}`"),
            "Missing shared table definition: {$table}."
        );
    }
    foreach (['VoterJohn', 'VoterAmina', 'VoterBenjamin', 'VoterChen', 'VoterDevi', 'VoterFarah'] as $username) {
        assertTrue(str_contains($sql, $username), "Missing demo voter: {$username}.");
    }
    assertTrue(str_contains($sql, '`candidate_id` INT(11) NOT NULL'), 'Vote candidate_id mapping is missing.');
    assertTrue(str_contains($sql, 'unique_voter_per_election'), 'One-vote-per-election constraint is missing.');
};

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[PASS] {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "[FAIL] {$name}\n       {$error->getMessage()}\n";
    }
}

echo PHP_EOL . (count($tests) - $failures) . '/' . count($tests) . " tests passed.\n";
exit($failures === 0 ? 0 : 1);
