<?php

// PIC: Risky Puah Kaho - Payment Module — provider endpoints consumed by Election and Candidate.

use App\Http\Controllers\Api\CandidateDepositRefundController;
use App\Http\Controllers\Api\PaymentPingController;
use App\Http\Controllers\Api\PaymentStatusController;
use App\Http\Controllers\Api\PaymentVerifyController;
use App\Http\Middleware\ValidateCandidateRefundRequest;
use App\Http\Middleware\ValidatePaymentStatusRequest;
use App\Http\Middleware\ValidatePaymentVerifyRequest;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('v1')->group(function (): void {
    Route::get('/payments/ping', PaymentPingController::class)
        ->name('api.payments.ping');

    Route::post('/payments/verify', PaymentVerifyController::class)
        ->middleware(ValidatePaymentVerifyRequest::class)
        ->name('api.payments.verify');

    Route::get('/payments/{payment}/status', PaymentStatusController::class)
        ->whereNumber('payment')
        ->middleware(ValidatePaymentStatusRequest::class)
        ->name('api.payments.status');

    Route::post('/payments/{payment}/refund', CandidateDepositRefundController::class)
        ->whereNumber('payment')
        ->middleware(ValidateCandidateRefundRequest::class)
        ->name('api.payments.candidate-refund');
});
