<?php

declare(strict_types=1);

namespace VoteTrust\Voting\Controller;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use VoteTrust\Voting\Model\VoteAlreadyExists;
use VoteTrust\Voting\Model\VoteStorage;
use VoteTrust\Voting\Service\VoteAccessDenied;
use VoteTrust\Voting\Service\VoteCastingService;
use VoteTrust\Voting\Service\VotingIntegration;
use VoteTrust\Voting\Service\IntegrationException;

final class VotingController
{
    public function __construct(
        private readonly VoteCastingService $service,
        private readonly VoteStorage $votes,
        private readonly VotingIntegration $api,
        private readonly PDO $database,
        private readonly string $apiKey,
        private readonly string $viewPath,
        private readonly string $baseUrl = '',
    ) {}

    /** List elections currently accepted by the Election Module. */
    public function index(): void
    {
        try {
            $voter = $this->sessionVoter();
            [$requestId, $timestamp] = $this->newEnvelope();
            $elections = $this->api->openElections($requestId, $timestamp);
            foreach ($elections as &$election) {
                $election['hasVoted'] = $this->votes->hasVoted($voter['id'], (int) $election['id']);
            }
            unset($election);

            $this->view('index.php', compact('voter', 'elections'));
        } catch (Throwable $error) {
            $this->showError($error);
        }
    }

    /** Retrieve lifecycle permission from Election and ballot rows from the shared schema. */
    public function ballot(): void
    {
        try {
            $voter = $this->sessionVoter();
            $electionId = $this->positiveId($_GET['electionId'] ?? null, 'election');
            [$requestId, $timestamp] = $this->newEnvelope();

            if (!$this->api->voterIsEligible($voter['id'], $electionId, $requestId, $timestamp)) {
                throw new VoteAccessDenied('You are not eligible to vote in this election.');
            }
            $election = $this->api->election($electionId, $requestId, $timestamp);
            if ($electionId !== $election['id']
                || strtoupper($election['status']) !== 'VOTING_OPEN'
                || !$election['votingAllowed']
            ) {
                throw new RuntimeException('This election is not open for voting.');
            }

            $_SESSION['voting_csrf'] ??= bin2hex(random_bytes(32));
            $csrf = (string) $_SESSION['voting_csrf'];
            $hasVoted = $this->votes->hasVoted($voter['id'], $electionId);
            $this->view('ballot.php', compact(
                'voter', 'election', 'requestId', 'timestamp', 'csrf', 'hasVoted'
            ));
        } catch (Throwable $error) {
            $this->showError($error);
        }
    }

    /** Validate the browser request and invoke the decorated vote service. */
    public function cast(): void
    {
        try {
            $this->checkCsrf($_POST['csrf'] ?? null);
            $voter = $this->sessionVoter();
            $request = [
                'voterId' => $voter['id'],
                'electionId' => $this->positiveId($_POST['electionId'] ?? null, 'election'),
                'candidateId' => $this->positiveId($_POST['candidateId'] ?? null, 'candidate'),
                'requestID' => $this->requestId($_POST['requestID'] ?? null),
                'timestamp' => $this->freshTimestamp($_POST['timestamp'] ?? null),
            ];

            $vote = $this->service->cast($request);
            unset($_SESSION['voting_csrf']);
            $this->view('success.php', compact('vote', 'voter'), 201);
        } catch (Throwable $error) {
            $this->showError($error);
        }
    }

    /** API provided to User/Election modules; candidate choice is never revealed. */
    public function statusApi(): void
    {
        try {
            $this->checkApiKey();
            $requestId = $this->requestId($_GET['requestID'] ?? null);
            $this->freshTimestamp($_GET['timestamp'] ?? null);
            $voterId = $this->positiveId($_GET['voterId'] ?? null, 'voter');
            $electionId = $this->positiveId($_GET['electionId'] ?? null, 'election');
            $this->jsonSuccess($requestId, [
                'voterId' => $voterId,
                'electionId' => $electionId,
                'hasVoted' => $this->votes->hasVoted($voterId, $electionId),
            ], 'Voting status retrieved.');
        } catch (Throwable $error) {
            $this->apiError($error);
        }
    }

    /** API provided to Election/Results modules after Election confirms closure. */
    public function tallyApi(): void
    {
        try {
            $this->checkApiKey();
            $requestId = $this->requestId($_GET['requestID'] ?? null);
            $timestamp = $this->freshTimestamp($_GET['timestamp'] ?? null);
            $electionId = $this->positiveId($_GET['electionId'] ?? null, 'election');
            $election = $this->api->election($electionId, $requestId, $timestamp);

            if ($electionId !== $election['id'] || strtoupper($election['status']) !== 'CLOSED') {
                throw new RuntimeException('Votes can only be tallied after the election closes.');
            }

            $counts = $this->votes->tally($electionId);
            $tally = [];
            foreach ($election['candidacies'] as $candidacy) {
                $candidateId = (int) $candidacy['candidateId'];
                $tally[] = [
                    'candidateId' => $candidateId,
                    'candidateName' => (string) $candidacy['name'],
                    'votes' => $counts[$candidateId] ?? 0,
                ];
            }
            $this->jsonSuccess($requestId, ['electionId' => $electionId, 'tally' => $tally], 'Vote tally retrieved.');
        } catch (Throwable $error) {
            $this->apiError($error);
        }
    }

    /** @return array{id:int,name:string} */
    private function sessionVoter(): array
    {
        $id = $_SESSION['authenticated_user_id'] ?? $_SESSION['user_id'] ?? null;
        if ($id === null) {
            throw new VoteAccessDenied('You must sign in before voting.');
        }
        $voterId = $this->positiveId($id, 'voter');
        $query = $this->database->prepare(
            'SELECT u.id, u.username FROM voter v JOIN `user` u ON u.id = v.id WHERE v.id = :id LIMIT 1'
        );
        $query->execute(['id' => $voterId]);
        $voter = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($voter)) {
            throw new VoteAccessDenied('Your signed-in account is not registered as a voter.');
        }

        return ['id' => (int) $voter['id'], 'name' => (string) $voter['username']];
    }

    private function positiveId(mixed $value, string $name): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new RuntimeException("Invalid {$name} ID.");
        }

        return (int) $validated;
    }

    private function requestId(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $value)) {
            throw new RuntimeException('Invalid request ID.');
        }

        return $value;
    }

    private function freshTimestamp(mixed $value): string
    {
        try {
            $time = is_string($value) ? new DateTimeImmutable($value) : null;
        } catch (Throwable) {
            $time = null;
        }
        if (!$time instanceof DateTimeImmutable || abs(time() - $time->getTimestamp()) > 300) {
            throw new RuntimeException('The request timestamp is invalid or expired.');
        }

        return (string) $value;
    }

    /** @return array{string,string} */
    private function newEnvelope(): array
    {
        return ['VOTE-' . strtoupper(bin2hex(random_bytes(12))), (new DateTimeImmutable())->format(DATE_ATOM)];
    }

    private function checkCsrf(mixed $token): void
    {
        if (!is_string($token) || !isset($_SESSION['voting_csrf'])
            || !hash_equals((string) $_SESSION['voting_csrf'], $token)
        ) {
            throw new VoteAccessDenied('The voting form is invalid. Please reload it.');
        }
    }

    private function checkApiKey(): void
    {
        $provided = $_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? '';
        if ($this->apiKey === '' || !is_string($provided) || !hash_equals($this->apiKey, $provided)) {
            throw new VoteAccessDenied('Voting API access denied.');
        }
    }

    private function showError(Throwable $error): void
    {
        if ($error instanceof PDOException) {
            error_log('Voting module failure: ' . $error->getMessage());
            $message = 'The voting service is temporarily unavailable.';
            $this->view('error.php', compact('message'), 500);
            return;
        }

        $status = match (true) {
            $error instanceof VoteAccessDenied => 403,
            $error instanceof VoteAlreadyExists => 409,
            $error instanceof IntegrationException => 502,
            $error instanceof RuntimeException => 422,
            default => 500,
        };
        if ($status === 500) {
            error_log('Voting module failure: ' . $error->getMessage());
        }
        $message = $status === 500 ? 'The voting service is temporarily unavailable.' : $error->getMessage();
        $this->view('error.php', compact('message'), $status);
    }

    private function apiError(Throwable $error): void
    {
        $requestId = is_string($_GET['requestID'] ?? null) ? $_GET['requestID'] : 'UNKNOWN';
        $httpStatus = match (true) {
            $error instanceof VoteAccessDenied => 403,
            $error instanceof VoteAlreadyExists => 409,
            $error instanceof IntegrationException => 502,
            $error instanceof RuntimeException => 422,
            default => 500,
        };
        if ($error instanceof PDOException || $httpStatus === 500) {
            error_log('Voting API failure: ' . $error->getMessage());
            $this->jsonFailure($requestId, 'The voting service is temporarily unavailable.', $httpStatus, 'E');
            return;
        }
        $this->jsonFailure($requestId, $error->getMessage(), $httpStatus);
    }

    /** @param array<string, mixed> $data */
    private function view(string $file, array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $url = fn (string $path): string => $this->baseUrl . '/' . ltrim($path, '/');
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->viewPath . '/' . $file;
        $content = (string) ob_get_clean();
        require $this->viewPath . '/layout.php';
    }

    /** @param array<string, mixed> $data */
    private function jsonSuccess(string $requestId, array $data, string $message): void
    {
        $this->json([
            'status' => 'S',
            'message' => $message,
            'requestID' => $requestId,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'data' => $data,
        ]);
    }

    private function jsonFailure(string $requestId, string $message, int $httpStatus, string $status = 'F'): void
    {
        $this->json([
            'status' => $status,
            'message' => $message,
            'requestID' => $requestId,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'errors' => [],
        ], $httpStatus);
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
