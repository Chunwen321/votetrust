<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drives the real /api/v1/payments/verify and /api/v1/payments/{id}/status
 * routes in-process (through the actual HTTP kernel, middleware, and mock
 * gateway) so the Payment module can be smoke-tested without a running web
 * server. All writes happen inside a transaction that is always rolled back,
 * so it never leaves test data behind in the real database.
 */
final class PaymentModuleSmokeTest extends Command
{
    protected $signature = 'payment:smoke-test';

    protected $description = 'Exercise the Payment module verify/status endpoints against the mock gateway';

    private int $passed = 0;

    private int $failed = 0;

    public function handle(Kernel $kernel): int
    {
        config([
            'payment.api.key' => 'smoke-test-payment-key',
            'payment.candidate_api.key' => 'smoke-test-candidate-key',
        ]);

        DB::beginTransaction();

        try {
            $this->runScenarios($kernel);
        } catch (Throwable $exception) {
            $this->components->error('Smoke test crashed: '.$exception->getMessage());
            $this->failed++;
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->components->info("Passed: {$this->passed}  Failed: {$this->failed}  (database rolled back, nothing persisted)");

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function runScenarios(Kernel $kernel): void
    {
        $organizer = User::factory()->create();
        $candidate = User::factory()->create();
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Smoke Test Election',
            'position_sought' => 'Smoke Tester',
            'status' => 'PENDING_PAYMENT',
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);
        $candidacy = Candidacy::query()->create([
            'user_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
            'status' => 'Pending',
            'registered_at' => now(),
        ]);

        // 1. Election hosting payment: a confirmable reference should settle Paid.
        $verifyOk = $this->dispatch($kernel, 'POST', '/api/v1/payments/verify', [
            'X-API-Key' => 'smoke-test-payment-key',
        ], [
            'requestID' => 'SMOKE-EL-'.uniqid(),
            'timestamp' => now()->toAtomString(),
            'paymentReference' => 'VT-HOST-SMOKE-001',
            'purpose' => 'ELECTION_HOSTING',
            'payerId' => $organizer->getKey(),
            'electionId' => $election->getKey(),
            'expectedAmount' => 100,
            'currency' => 'MYR',
        ]);
        $this->check(
            'Election hosting payment confirms via mock gateway',
            $verifyOk['status'] === 200 && ($verifyOk['body']['data']['paymentStatus'] ?? null) === 'CONFIRMED',
            $verifyOk,
        );
        $hostingPaymentId = (int) ($verifyOk['body']['data']['paymentId'] ?? 0);
        $this->check(
            'Hosting payment persisted as Paid in the payment table',
            $hostingPaymentId > 0 && Payment::query()->whereKey($hostingPaymentId)->where('status', 'Paid')->exists(),
        );

        // 2. Election hosting payment: a reference ending -FAIL should decline.
        $verifyFail = $this->dispatch($kernel, 'POST', '/api/v1/payments/verify', [
            'X-API-Key' => 'smoke-test-payment-key',
        ], [
            'requestID' => 'SMOKE-EL-'.uniqid(),
            'timestamp' => now()->toAtomString(),
            'paymentReference' => 'VT-HOST-SMOKE-FAIL',
            'purpose' => 'ELECTION_HOSTING',
            'payerId' => $organizer->getKey(),
            'electionId' => $election->getKey(),
            'expectedAmount' => 100,
            'currency' => 'MYR',
        ]);
        $this->check(
            'A "-FAIL" reference is reported as FAILED',
            $verifyFail['status'] === 200 && ($verifyFail['body']['data']['paymentStatus'] ?? null) === 'FAILED',
            $verifyFail,
        );

        // 3. Verify endpoint rejects an invalid API key.
        $verifyUnauthorized = $this->dispatch($kernel, 'POST', '/api/v1/payments/verify', [
            'X-API-Key' => 'wrong-key',
        ], [
            'requestID' => 'SMOKE-EL-'.uniqid(),
            'timestamp' => now()->toAtomString(),
            'paymentReference' => 'VT-HOST-SMOKE-002',
            'purpose' => 'ELECTION_HOSTING',
            'payerId' => $organizer->getKey(),
            'electionId' => $election->getKey(),
            'expectedAmount' => 100,
            'currency' => 'MYR',
        ]);
        $this->check(
            'Verify endpoint rejects an invalid API key',
            $verifyUnauthorized['status'] === 401,
            $verifyUnauthorized,
        );

        // 4. Candidate deposit: a fresh Pending payment should be reported CONFIRMED
        //    (the mock gateway settles it) and the candidacy's own payment_id is used.
        $depositPayment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'organizer_id' => $organizer->getKey(),
            'candidate_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
            'purpose' => Payment::PURPOSE_CANDIDATE_DEPOSIT,
        ]);
        $candidacy->payment_id = $depositPayment->getKey();
        $candidacy->save();

        $statusOk = $this->dispatch($kernel, 'GET', '/api/v1/payments/'.$depositPayment->getKey().'/status', [
            'X-Payment-Api-Key' => 'smoke-test-candidate-key',
        ], [
            'candidateId' => $candidate->getKey(),
            'requestID' => 'SMOKE-CAN-'.uniqid(),
            'timestamp' => now()->toAtomString(),
        ], asQuery: true);
        $this->check(
            'Candidate deposit status confirms for the owning candidate',
            $statusOk['status'] === 200 && ($statusOk['body']['data']['paymentStatus'] ?? null) === 'CONFIRMED',
            $statusOk,
        );

        // 5. Status endpoint fails closed for a candidate who does not own the payment.
        $otherCandidate = User::factory()->create();
        $statusMismatch = $this->dispatch($kernel, 'GET', '/api/v1/payments/'.$depositPayment->getKey().'/status', [
            'X-Payment-Api-Key' => 'smoke-test-candidate-key',
        ], [
            'candidateId' => $otherCandidate->getKey(),
            'requestID' => 'SMOKE-CAN-'.uniqid(),
            'timestamp' => now()->toAtomString(),
        ], asQuery: true);
        $this->check(
            'Status endpoint returns 404 for a non-owning candidate',
            $statusMismatch['status'] === 404,
            $statusMismatch,
        );
    }

    /** @param array<string,string> $headers
     * @param  array<string,mixed>  $payload
     * @return array{status:int,body:array<mixed>}
     */
    private function dispatch(Kernel $kernel, string $method, string $uri, array $headers, array $payload, bool $asQuery = false): array
    {
        $request = $asQuery
            ? Request::create($uri, $method, $payload)
            : Request::create($uri, $method, [], [], [], [], (string) json_encode($payload));

        if (! $asQuery) {
            $request->headers->set('Content-Type', 'application/json');
        }
        $request->headers->set('Accept', 'application/json');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode((string) $response->getContent(), true) ?? [],
        ];
    }

    /** @param array{status:int,body:array<mixed>}|null $context */
    private function check(string $label, bool $passed, ?array $context = null): void
    {
        if ($passed) {
            $this->components->twoColumnDetail($label, '<fg=green>PASS</>');
            $this->passed++;

            return;
        }

        $this->components->twoColumnDetail($label, '<fg=red>FAIL</>');
        $this->failed++;
        if ($context !== null) {
            $this->line('    HTTP '.$context['status'].' '.json_encode($context['body']));
        }
    }
}
