<?php

declare(strict_types=1);

namespace VoteTrust\Voting\Service;

use PDO;

/**
 * Adapter for teammate modules.
 *
 * Low Wen Kai's availability API owns lifecycle decisions. The shared schema
 * supplies ballot details because that existing API does not expose candidates.
 */
final class ExternalApi implements VotingIntegration
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $electionUrl,
        private readonly string $userUrl,
        private readonly string $apiKey,
    ) {}

    public function openElections(string $requestId, string $timestamp): array
    {
        $query = $this->database->query(
            "SELECT id FROM election WHERE status = 'VOTING_OPEN' ORDER BY voting_end ASC"
        );
        $elections = [];

        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $availability = $this->availability((int) $id, $requestId, $timestamp);
            if (($availability['votingAllowed'] ?? false) !== true) {
                continue;
            }

            $election = $this->electionRecord((int) $id);
            $elections[] = [
                'id' => (int) $election['id'],
                'title' => (string) $election['title'],
                'description' => (string) ($election['description'] ?? ''),
                'status' => (string) $availability['lifecycleState'],
                'votingPeriod' => is_array($availability['votingPeriod'] ?? null)
                    ? $availability['votingPeriod']
                    : [],
            ];
        }

        return $elections;
    }

    public function election(int $electionId, string $requestId, string $timestamp): array
    {
        $availability = $this->availability($electionId, $requestId, $timestamp);
        $election = $this->electionRecord($electionId);
        $query = $this->database->prepare(
            "SELECT c.id, c.candidate_id, u.username
             FROM candidacy c
             JOIN candidate candidate_role ON candidate_role.id = c.candidate_id
             JOIN `user` u ON u.id = candidate_role.id
             WHERE c.election_id = :election AND c.status = 'Approved'
             ORDER BY c.registered_at ASC, c.id ASC"
        );
        $query->execute(['election' => $electionId]);
        $candidacies = array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'candidateId' => (int) $row['candidate_id'],
                'name' => (string) $row['username'],
            ],
            $query->fetchAll(PDO::FETCH_ASSOC)
        );

        return [
            'id' => (int) $election['id'],
            'title' => (string) $election['title'],
            'description' => (string) ($election['description'] ?? ''),
            'eligibilityRules' => (string) ($election['eligibility_rules'] ?? ''),
            'status' => (string) ($availability['lifecycleState'] ?? $election['status']),
            'votingAllowed' => ($availability['votingAllowed'] ?? false) === true,
            'votingPeriod' => is_array($availability['votingPeriod'] ?? null)
                ? $availability['votingPeriod']
                : [],
            'candidacies' => $candidacies,
        ];
    }

    public function voterIsEligible(
        int $voterId,
        int $electionId,
        string $requestId,
        string $timestamp
    ): bool {
        if ($this->userUrl !== '') {
            $response = $this->get($this->userUrl . '/api/v1/voters/eligibility', [
                'voterId' => $voterId,
                'electionId' => $electionId,
                'requestID' => $requestId,
                'timestamp' => $timestamp,
            ]);
            if (!hash_equals($requestId, (string) $response['requestID'])) {
                throw new IntegrationException('The User Module returned a mismatched request ID.');
            }

            return ($response['data']['eligible'] ?? $response['eligible'] ?? false) === true;
        }

        $query = $this->database->prepare('SELECT 1 FROM voter WHERE id = :id LIMIT 1');
        $query->execute(['id' => $voterId]);

        return $query->fetchColumn() !== false;
    }

    /** @return array<string, mixed> */
    private function availability(int $electionId, string $requestId, string $timestamp): array
    {
        $response = $this->get(
            $this->electionUrl . '/api/v1/elections/' . $electionId . '/availability',
            ['requestID' => $requestId, 'timestamp' => $timestamp],
        );
        if (!hash_equals($requestId, (string) $response['requestID'])) {
            throw new IntegrationException('The Election Module returned a mismatched request ID.');
        }
        $availability = $response['data'] ?? null;
        if (!is_array($availability) || (int) ($availability['electionId'] ?? 0) !== $electionId) {
            throw new IntegrationException('The Election Module returned invalid availability data.');
        }

        return $availability;
    }

    /** @return array<string, mixed> */
    private function electionRecord(int $electionId): array
    {
        $query = $this->database->prepare(
            'SELECT id, title, description, eligibility_rules, status
             FROM election WHERE id = :id LIMIT 1'
        );
        $query->execute(['id' => $electionId]);
        $election = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($election)) {
            throw new IntegrationException('Election not found.');
        }

        return $election;
    }

    /** @return array<string, mixed> */
    private function get(string $url, array $query): array
    {
        $headers = "Accept: application/json\r\n";
        if ($this->apiKey !== '') {
            $headers .= "X-Internal-Api-Key: {$this->apiKey}\r\n";
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => $headers,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url . '?' . http_build_query($query), false, $context);
        $statusLine = $http_response_header[0] ?? '';

        if ($body === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new IntegrationException('A required module API is unavailable.');
        }

        $response = json_decode($body, true);
        if (!is_array($response)
            || ($response['status'] ?? null) !== 'S'
            || !isset($response['requestID'], $response['timestamp'], $response['data'])
        ) {
            throw new IntegrationException('A required module returned an invalid IFA response.');
        }

        return $response;
    }
}
