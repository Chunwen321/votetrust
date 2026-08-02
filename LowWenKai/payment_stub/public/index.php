<?php

declare(strict_types=1);

use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Exceptions\ValidationException;
use VoteTrust\Election\Security\ApiRequestValidator;
use VoteTrust\Election\Services\ApiEnvelope;

$root = dirname(__DIR__, 2);
require $root . '/autoload.php';
Config::load($root);
date_default_timezone_set((string) Config::get('APP_TIMEZONE', 'Asia/Kuala_Lumpur'));

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$request = Request::capture();
$requestId = 'UNKNOWN';

try {
    if ($request->method() !== 'POST' || $request->path() !== '/api/v1/payments/verify') {
        Response::json(ApiEnvelope::failure('UNKNOWN', 'Endpoint not found.', [], 'F'), 404)->send();
        return;
    }

    $configuredKey = (string) Config::get('PAYMENT_API_KEY', 'local-development-key');
    $providedKey = (string) $request->header('X-API-Key', '');
    if ($providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
        Response::json(ApiEnvelope::failure('UNKNOWN', 'Invalid API key.', [], 'F'), 401)->send();
        return;
    }

    $payload = $request->json();
    $envelope = (new ApiRequestValidator())->validateEnvelope($payload);
    $requestId = $envelope['requestID'];
    $reference = trim((string) ($payload['paymentReference'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $reference)) {
        throw new ValidationException(['paymentReference' => 'A valid payment reference is required.']);
    }

    $recordsPath = dirname(__DIR__) . '/data/payments.json';
    $records = is_file($recordsPath) ? json_decode((string) file_get_contents($recordsPath), true) : [];
    $record = is_array($records) ? ($records[$reference] ?? null) : null;

    if (!is_array($record)) {
        Response::json(ApiEnvelope::failure($requestId, 'Payment reference was not found.', [
            'paymentReference' => $reference,
        ]), 200)->send();
        return;
    }

    $data = [
        'paymentReference' => $reference,
        'paymentStatus' => (string) $record['paymentStatus'],
        'amount' => (float) $record['amount'],
        'currency' => (string) $record['currency'],
        'payerId' => (int) $record['payerId'],
        'purpose' => (string) $record['purpose'],
        'paymentId' => isset($record['paymentId']) && is_numeric($record['paymentId']) ? (int) $record['paymentId'] : null,
    ];

    $confirmed = $data['paymentStatus'] === 'CONFIRMED';
    $response = $confirmed
        ? ApiEnvelope::success($requestId, $data, 'Hosting payment confirmed.')
        : ApiEnvelope::failure($requestId, 'Payment has not been confirmed.', $data);
    if (!$confirmed) {
        $response['data'] = $data;
    }
    Response::json($response)->send();
} catch (ValidationException $exception) {
    Response::json(ApiEnvelope::failure($requestId, $exception->getMessage(), $exception->errors), 422)->send();
} catch (Throwable $exception) {
    Response::json(ApiEnvelope::failure($requestId, 'Payment sandbox error.', [], 'E'), 500)->send();
}
