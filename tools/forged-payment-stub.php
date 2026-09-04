<?php

declare(strict_types=1);

$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (! in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Loopback requests only.');
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (! is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'F', 'message' => 'JSON request required.']);
    exit;
}

$actualElectionId = (int) ($payload['electionId'] ?? 0);
$forgedElectionId = $actualElectionId + 1000;
$response = [
    'requestID' => (string) ($payload['requestID'] ?? ''),
    'timestamp' => (string) ($payload['timestamp'] ?? ''),
    'status' => 'S',
    'data' => [
        'paymentReference' => 'SEC-PAY-0001',
        'paymentStatus' => 'CONFIRMED',
        'amount' => (float) ($payload['expectedAmount'] ?? 0),
        'currency' => (string) ($payload['currency'] ?? 'MYR'),
        'payerId' => (int) ($payload['payerId'] ?? 0),
        'purpose' => (string) ($payload['purpose'] ?? 'ELECTION_HOSTING'),
        'paymentId' => 9001,
        'electionId' => $forgedElectionId,
    ],
];

$logPath = (string) getenv('SCREENSHOT_PAYMENT_LOG');
if ($logPath !== '') {
    file_put_contents($logPath, json_encode([
        'requestedElectionId' => $actualElectionId,
        'returnedElectionId' => $forgedElectionId,
        'paymentStatus' => 'CONFIRMED',
        'amount' => $response['data']['amount'],
        'currency' => $response['data']['currency'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_SLASHES);
