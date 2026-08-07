<?php

declare(strict_types=1);

namespace VoteTrust\Voting\Model;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

interface VoteStorage
{
    public function hasVoted(int $voterId, int $electionId): bool;
    public function save(Vote $vote): Vote;
    /** @return array<int, int> */
    public function tally(int $electionId): array;
    public function locked(int $voterId, int $electionId, callable $work): mixed;
}

/**
 * Object-Relational Mapper for Vote objects.
 * All SQL for the Voting Process module is kept inside this model class.
 */
final class VoteORM implements VoteStorage
{
    public function __construct(private readonly PDO $database) {}

    public function hasVoted(int $voterId, int $electionId): bool
    {
        $query = $this->database->prepare(
            'SELECT 1 FROM `vote` WHERE voter_id = :voter AND election_id = :election LIMIT 1'
        );
        $query->execute(['voter' => $voterId, 'election' => $electionId]);

        return $query->fetchColumn() !== false;
    }

    public function save(Vote $vote): Vote
    {
        $query = $this->database->prepare(
            'INSERT INTO `vote` (voter_id, election_id, candidate_id, cast_at)
             VALUES (:voter, :election, :candidate, :cast_at)'
        );

        try {
            $query->execute([
                'voter' => $vote->voterId,
                'election' => $vote->electionId,
                'candidate' => $vote->candidateId,
                'cast_at' => $vote->castAt->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $error) {
            $driverCode = (int) ($error->errorInfo[1] ?? 0);
            if ($driverCode === 1062 || str_contains(strtoupper($error->getMessage()), 'UNIQUE')) {
                throw new VoteAlreadyExists('You have already voted in this election.', 0, $error);
            }
            throw $error;
        }

        return $vote->withId((int) $this->database->lastInsertId());
    }

    /** @return array<int, int> candidate ID => vote count */
    public function tally(int $electionId): array
    {
        $query = $this->database->prepare(
            'SELECT candidate_id, COUNT(*) AS total
             FROM `vote` WHERE election_id = :election
             GROUP BY candidate_id'
        );
        $query->execute(['election' => $electionId]);

        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['candidate_id']] = (int) $row['total'];
        }

        return $result;
    }

    /** Lock one voter/election pair so simultaneous requests cannot both succeed. */
    public function locked(int $voterId, int $electionId, callable $work): mixed
    {
        $isMySql = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $lockName = 'vote:' . hash('sha256', $voterId . '|' . $electionId);
        if ($isMySql) {
            $lock = $this->database->prepare('SELECT GET_LOCK(:name, 5)');
            $lock->execute(['name' => $lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                throw new RuntimeException('Your vote is already being processed.');
            }
        }

        $ownsTransaction = !$this->database->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->database->beginTransaction();
            }
            $result = $work();
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $result;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        } finally {
            if ($isMySql) {
                $release = $this->database->prepare('SELECT RELEASE_LOCK(:name)');
                $release->execute(['name' => $lockName]);
            }
        }
    }
}

final class VoteAlreadyExists extends RuntimeException {}
