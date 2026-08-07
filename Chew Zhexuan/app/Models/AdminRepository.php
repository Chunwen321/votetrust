<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Models;

use PDO;
use VoteTrust\UserAdmin\Core\Database;

final class AdminRepository
{
    /** @return array{elections:int,active:int,voters:int,pending_candidates:int} */
    public function dashboardCounts(): array
    {
        $pdo = Database::connection();

        return [
            'elections' => (int) $pdo->query('SELECT COUNT(*) FROM `election`')->fetchColumn(),
            'active' => (int) $pdo->query("SELECT COUNT(*) FROM `election` WHERE `status` IN ('NOMINATION_OPEN', 'VOTING_OPEN', 'APPROVED')")->fetchColumn(),
            'voters' => (int) $pdo->query("SELECT COUNT(*) FROM `voter` WHERE `status` = 'APPROVED'")->fetchColumn(),
            'pending_candidates' => (int) $pdo->query("SELECT COUNT(*) FROM `candidacy` WHERE `status` = 'Pending'")->fetchColumn(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listElections(): array
    {
        $statement = Database::connection()->query($this->electionSelect() . ' GROUP BY e.id ORDER BY e.updated_at DESC, e.id DESC');

        /** @var list<array<string, mixed>> $elections */
        $elections = $statement->fetchAll();

        return $elections;
    }

    /** @return array<string, mixed>|null */
    public function findElection(int $electionId): ?array
    {
        $statement = Database::connection()->prepare($this->electionSelect() . ' WHERE e.id = :id GROUP BY e.id LIMIT 1');
        $statement->execute([':id' => $electionId]);
        $election = $statement->fetch();

        return is_array($election) ? $election : null;
    }

    /** @return list<array<string, mixed>> */
    public function listVoters(int $electionId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT v.user_id, v.election_id, v.status, v.created_at, v.updated_at,
                    u.username, u.full_name, u.email, u.account_status
             FROM `voter` v
             INNER JOIN `user` u ON u.id = v.user_id
             WHERE v.election_id = :election_id
             ORDER BY FIELD(v.status, \'PENDING\', \'REJECTED\', \'SUSPENDED\', \'APPROVED\'), u.username ASC'
        );
        $statement->execute([':election_id' => $electionId]);

        /** @var list<array<string, mixed>> $voters */
        $voters = $statement->fetchAll();

        return $voters;
    }

    /** @return list<array<string, mixed>> */
    public function listCandidates(int $electionId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.candidate_id, c.election_id, c.status, c.registered_at,
                    u.username, u.full_name, u.email, u.account_status
             FROM `candidacy` c
             INNER JOIN `user` u ON u.id = c.candidate_id
             WHERE c.election_id = :election_id
             ORDER BY FIELD(c.status, \'Pending\', \'Rejected\', \'Approved\'), u.username ASC'
        );
        $statement->execute([':election_id' => $electionId]);

        /** @var list<array<string, mixed>> $candidates */
        $candidates = $statement->fetchAll();

        return $candidates;
    }

    /** @return list<array<string, mixed>> */
    public function listEligibleUsers(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, username, full_name, email
             FROM `user`
             WHERE is_admin = 0 AND COALESCE(account_status, \'ACTIVE\') = \'ACTIVE\'
             ORDER BY username ASC'
        );

        /** @var list<array<string, mixed>> $users */
        $users = $statement->fetchAll();

        return $users;
    }

    /** @param array<string, mixed> $values */
    public function createElection(array $values, int $adminId): int
    {
        return Database::transaction(function (PDO $pdo) use ($values, $adminId): int {
            $statement = $pdo->prepare(
                'INSERT INTO `election` (
                    organizer_id, title, description, eligibility_rules,
                    nomination_start, nomination_end, voting_start, voting_end,
                    hosting_fee, currency, status, transition_history_json, version, created_at, updated_at
                 ) VALUES (
                    NULL, :title, :description, :eligibility_rules,
                    :nomination_start, :nomination_end, :voting_start, :voting_end,
                    :hosting_fee, :currency, \'DRAFT\', :history, 1, NOW(), NOW()
                 )'
            );
            $statement->execute([
                ...$this->electionParameters($values),
                ':history' => $this->history([], 'ADMIN_CREATED', $adminId),
            ]);

            return (int) $pdo->lastInsertId();
        });
    }

    /** @param array<string, mixed> $values */
    public function updateElection(int $electionId, array $values, int $adminId): void
    {
        Database::transaction(function (PDO $pdo) use ($electionId, $values, $adminId): void {
            $existing = $this->findForUpdate($pdo, $electionId);
            $statement = $pdo->prepare(
                'UPDATE `election`
                 SET title = :title,
                     description = :description,
                     eligibility_rules = :eligibility_rules,
                     nomination_start = :nomination_start,
                     nomination_end = :nomination_end,
                     voting_start = :voting_start,
                     voting_end = :voting_end,
                     hosting_fee = :hosting_fee,
                     currency = :currency,
                     transition_history_json = :history,
                     version = version + 1,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                ...$this->electionParameters($values),
                ':history' => $this->history($existing['transition_history_json'], 'ADMIN_UPDATED', $adminId),
                ':id' => $electionId,
            ]);
        });
    }

    public function changeElectionStatus(int $electionId, string $status, int $adminId): void
    {
        Database::transaction(function (PDO $pdo) use ($electionId, $status, $adminId): void {
            $existing = $this->findForUpdate($pdo, $electionId);
            $statement = $pdo->prepare(
                'UPDATE `election`
                 SET status = :status,
                     approved_by = CASE WHEN :set_approved_by THEN :admin_id ELSE approved_by END,
                     approved_at = CASE WHEN :set_approved_at THEN NOW() ELSE approved_at END,
                     transition_history_json = :history,
                     version = version + 1,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                ':status' => $status,
                ':set_approved_by' => $status === 'NOMINATION_OPEN' ? 1 : 0,
                ':set_approved_at' => $status === 'NOMINATION_OPEN' ? 1 : 0,
                ':admin_id' => $adminId,
                ':history' => $this->history($existing['transition_history_json'], 'ADMIN_STATUS_' . $status, $adminId),
                ':id' => $electionId,
            ]);
        });
    }

    public function addOrUpdateVoter(int $electionId, int $userId, string $status, int $adminId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO `voter` (user_id, election_id, status, added_by, created_at, updated_at)
             VALUES (:user_id, :election_id, :status, :admin_id, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), added_by = VALUES(added_by), updated_at = NOW()'
        );
        $statement->execute([
            ':user_id' => $userId,
            ':election_id' => $electionId,
            ':status' => $status,
            ':admin_id' => $adminId,
        ]);
    }

    public function changeVoterStatus(int $electionId, int $userId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `voter` SET status = :status, updated_at = NOW()
             WHERE user_id = :user_id AND election_id = :election_id'
        );
        $statement->execute([':status' => $status, ':user_id' => $userId, ':election_id' => $electionId]);
    }

    public function addOrUpdateCandidate(int $electionId, int $userId, string $status): void
    {
        Database::transaction(function (PDO $pdo) use ($electionId, $userId, $status): void {
            $pdo->prepare('INSERT IGNORE INTO `candidate` (id) VALUES (:id)')->execute([':id' => $userId]);
            $statement = $pdo->prepare(
                'INSERT INTO `candidacy` (candidate_id, election_id, status)
                 VALUES (:candidate_id, :election_id, :status)
                 ON DUPLICATE KEY UPDATE status = VALUES(status)'
            );
            $statement->execute([':candidate_id' => $userId, ':election_id' => $electionId, ':status' => $status]);
        });
    }

    public function changeCandidateStatus(int $electionId, int $candidateId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `candidacy` SET status = :status WHERE candidate_id = :candidate_id AND election_id = :election_id'
        );
        $statement->execute([':status' => $status, ':candidate_id' => $candidateId, ':election_id' => $electionId]);
    }

    /** @return array<string, mixed> */
    private function findForUpdate(PDO $pdo, int $electionId): array
    {
        $statement = $pdo->prepare('SELECT transition_history_json FROM `election` WHERE id = :id FOR UPDATE');
        $statement->execute([':id' => $electionId]);
        $election = $statement->fetch();
        if (!is_array($election)) {
            throw new \RuntimeException('Election not found.');
        }

        return $election;
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    private function electionParameters(array $values): array
    {
        return [
            ':title' => $values['title'],
            ':description' => $values['description'],
            ':eligibility_rules' => $values['eligibility_rules'],
            ':nomination_start' => $values['nomination_start'],
            ':nomination_end' => $values['nomination_end'],
            ':voting_start' => $values['voting_start'],
            ':voting_end' => $values['voting_end'],
            ':hosting_fee' => $values['hosting_fee'],
            ':currency' => $values['currency'],
        ];
    }

    private function history(mixed $current, string $action, int $adminId): string
    {
        $history = json_decode(is_string($current) ? $current : '[]', true);
        $history = is_array($history) ? $history : [];
        $history[] = [
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'action' => $action,
            'actorId' => $adminId,
        ];

        return json_encode(array_slice($history, -30), JSON_THROW_ON_ERROR);
    }

    private function electionSelect(): string
    {
        return 'SELECT e.*, organizer.username AS organizer_username,
                    COUNT(DISTINCT v.user_id) AS voter_total,
                    COUNT(DISTINCT CASE WHEN v.status = \'APPROVED\' THEN v.user_id END) AS approved_voter_total,
                    COUNT(DISTINCT c.id) AS candidate_total,
                    COUNT(DISTINCT CASE WHEN c.status = \'Approved\' THEN c.id END) AS approved_candidate_total
                FROM `election` e
                LEFT JOIN `user` organizer ON organizer.id = e.organizer_id
                LEFT JOIN `voter` v ON v.election_id = e.id
                LEFT JOIN `candidacy` c ON c.election_id = e.id';
    }
}
