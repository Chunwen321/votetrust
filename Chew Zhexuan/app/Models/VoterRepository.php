<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Models;

use VoteTrust\UserAdmin\Core\Database;

final class VoterRepository
{
    /** @return array<string, mixed>|null */
    public function findEligibility(int $voterId, int $electionId): ?array
    {
        $statement = Database::connection()->prepare(
            $this->eligibilitySelect() . ' WHERE v.user_id = :voter_id AND v.election_id = :election_id LIMIT 1'
        );
        $statement->execute([':voter_id' => $voterId, ':election_id' => $electionId]);
        $eligibility = $statement->fetch();

        return is_array($eligibility) ? $eligibility : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForVoter(int $voterId): array
    {
        $statement = Database::connection()->prepare(
            $this->eligibilitySelect() . ' WHERE v.user_id = :voter_id ORDER BY v.updated_at DESC, v.election_id DESC'
        );
        $statement->execute([':voter_id' => $voterId]);

        /** @var list<array<string, mixed>> $enrolments */
        $enrolments = $statement->fetchAll();

        return $enrolments;
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(?string $status): array
    {
        $sql = $this->eligibilitySelect();
        $parameters = [];
        if ($status !== null) {
            $sql .= ' WHERE v.status = :status';
            $parameters[':status'] = $status;
        }
        $sql .= ' ORDER BY FIELD(v.status, \'PENDING\', \'REJECTED\', \'SUSPENDED\', \'APPROVED\'), v.updated_at ASC, v.election_id ASC, v.user_id ASC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $enrolments */
        $enrolments = $statement->fetchAll();

        return $enrolments;
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        $statement = Database::connection()->query(
            'SELECT `status`, COUNT(*) AS total FROM `voter` GROUP BY `status`'
        );
        $counts = ['PENDING' => 0, 'APPROVED' => 0, 'REJECTED' => 0, 'SUSPENDED' => 0];
        foreach ($statement->fetchAll() as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function updateStatus(int $voterId, int $electionId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `voter`
             SET `status` = :status,
                 `updated_at` = NOW()
             WHERE `user_id` = :voter_id AND `election_id` = :election_id'
        );
        $statement->execute([
            ':status' => $status,
            ':voter_id' => $voterId,
            ':election_id' => $electionId,
        ]);
    }

    public function electionExists(int $electionId): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM `election` WHERE `id` = :id LIMIT 1');
        $statement->execute([':id' => $electionId]);

        return $statement->fetchColumn() !== false;
    }

    private function eligibilitySelect(): string
    {
        return 'SELECT
                    v.user_id AS voter_id,
                    v.election_id,
                    v.status AS eligibility_status,
                    v.created_at,
                    v.updated_at,
                    u.username,
                    u.full_name,
                    u.email,
                    u.account_status,
                    u.is_admin,
                    e.title AS election_title,
                    e.status AS election_status
                FROM `voter` v
                INNER JOIN `user` u ON u.id = v.user_id
                INNER JOIN `election` e ON e.id = v.election_id';
    }
}
