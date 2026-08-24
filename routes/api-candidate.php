<?php

// PIC: Wong Shun Bin — Candidate module REST APIs consumed by User administration and Payment.

declare(strict_types=1);

use App\Http\Controllers\Api\CandidateParticipantController;
use App\Http\Controllers\Api\CandidatePaymentInformationController;
use App\Http\Middleware\ValidateCandidateApiRequest;
use App\Http\Middleware\ValidateCandidateServiceRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', ValidateCandidateApiRequest::class])
    ->get('/v1/candidates/{candidate}/payment-information', CandidatePaymentInformationController::class)
    ->whereNumber('candidate')
    ->name('api.candidates.payment-information');

Route::middleware(['throttle:60,1', ValidateCandidateApiRequest::class])
    ->get('/v1/elections/{election}/candidates', CandidateParticipantController::class)
    ->whereNumber('election')
    ->name('api.elections.candidates');

Route::middleware(['throttle:60,1', ValidateCandidateServiceRequest::class])->group(function (): void {
    Route::post('/v1/elections/{election}/candidates', [CandidateParticipantController::class, 'store'])
        ->whereNumber('election')
        ->name('api.elections.candidates.store');
    Route::patch('/v1/elections/{election}/candidates/{user}', [CandidateParticipantController::class, 'update'])
        ->whereNumber('election')
        ->whereNumber('user')
        ->name('api.elections.candidates.update');
    Route::get('/v1/candidates/metrics', [CandidateParticipantController::class, 'metrics'])
        ->name('api.candidates.metrics');
});
