<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point (Facade pattern) for the Payment module. It hides the
 * pull clients that fetch Candidate/Election context, the pluggable
 * PaymentGatewayContract that settles a transaction, and Payment persistence
 * behind three simple operations: verify a hosting payment, report a
 * candidate deposit's status, and settle (confirm/fail/refund/cancel) a
 * payment on an administrator's instruction.
 */
final class PaymentFacade
{
    public function __construct(
        private readonly PaymentGatewayContract $gateway,
        private readonly CandidateInfoClient $candidateInfo,
        private readonly ElectionInfoClient $electionInfo,
    ) {}

    /**
     * @param  array{paymentReference:string,payerId:int,electionId:int,expectedAmount:float,currency:string,purpose:string}  $payload
     * @return array{paymentReference:string,paymentStatus:string,amount:float,currency:string,payerId:int,purpose:string,paymentId:int}
     */
    public function verifyHostingPayment(array $payload, string $requestId): array
    {
        $election = $this->electionInfo->fetch($payload['electionId']);
        if (! $election->available) {
            Log::info('payment.verify.election_pull_unavailable', ['requestID' => $requestId, 'electionId' => $payload['electionId']]);
        }

        $payment = Payment::query()
            ->where('purpose', Payment::PURPOSE_ELECTION_HOSTING)
            ->where('election_id', $payload['electionId'])
            ->where('provider_reference', $payload['paymentReference'])
            ->first();

        if (! $payment instanceof Payment) {
            if (! Election::query()->whereKey($payload['electionId'])->exists()) {
                throw new BusinessRuleException('Election not found for this payment reference.', 404);
            }

            $payment = new Payment;
            $payment->purpose = Payment::PURPOSE_ELECTION_HOSTING;
            $payment->election_id = $payload['electionId'];
            $payment->organizer_id = $payload['payerId'];
            $payment->amount = $payload['expectedAmount'];
            $payment->currency = strtoupper($payload['currency']);
            $payment->status = 'Pending';
            $payment->gateway = 'mock';
            $payment->provider_reference = $payload['paymentReference'];
            $payment->request_id = $requestId;
            $payment->save();
        }

        $settlement = $this->gateway->resolveStatus($payment);

        return [
            'paymentReference' => (string) $payment->provider_reference,
            'paymentStatus' => $settlement->status->providerStatus(),
            'amount' => (float) $payment->amount,
            'currency' => (string) $payment->currency,
            'payerId' => (int) $payment->organizer_id,
            'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
            'paymentId' => (int) $payment->getKey(),
        ];
    }

    /** @return array{paymentId:int,candidateId:int,paymentStatus:string}|null */
    public function candidatePaymentStatus(int $paymentId, int $candidateId, string $requestId): ?array
    {
        $payment = Payment::query()->find($paymentId);
        if (! $payment instanceof Payment || (int) $payment->candidate_id !== $candidateId) {
            return null;
        }

        if ($payment->election_id !== null) {
            $candidate = $this->candidateInfo->fetch($candidateId, (int) $payment->election_id);
            if (! $candidate->available) {
                Log::info('payment.status.candidate_pull_unavailable', ['requestID' => $requestId, 'candidateId' => $candidateId]);
            }
        }

        $settlement = $this->gateway->resolveStatus($payment);

        return [
            'paymentId' => (int) $payment->getKey(),
            'candidateId' => (int) $payment->candidate_id,
            'paymentStatus' => $settlement->status->providerStatus(),
        ];
    }

    public function confirm(Payment $payment): Payment
    {
        $this->gateway->confirm($payment);

        return $payment;
    }

    public function fail(Payment $payment, string $reason): Payment
    {
        $this->gateway->fail($payment, $reason);

        return $payment;
    }

    public function refund(Payment $payment, string $reason): Payment
    {
        $this->gateway->refund($payment, $reason);

        return $payment;
    }

    public function cancel(Payment $payment, string $reason): Payment
    {
        $this->gateway->cancel($payment, $reason);

        return $payment;
    }
}
