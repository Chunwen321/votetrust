<?php

use App\Http\Controllers\Api\CandidatePaymentInformationController;
use App\Http\Controllers\CandidacyCancellationController;
use App\Http\Controllers\CandidateDocumentController;
use App\Http\Controllers\CandidatePaymentController;
use App\Http\Controllers\CandidatePhotoController;
use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CandidateRegistrationController;
use App\Http\Middleware\ValidateCandidateApiRequest;
use Illuminate\Support\Facades\Route;

// PIC: Wong Shun Bin - Candidate information service consumed by the Payment module.
Route::middleware(['throttle:60,1', ValidateCandidateApiRequest::class])
    ->get('/api/v1/candidates/{candidate}/payment-information', CandidatePaymentInformationController::class)
    ->whereNumber('candidate')
    ->name('api.candidates.payment-information');

// PIC: Wong Shun Bin — Candidate Registration module routes.
Route::middleware(['auth', 'active.account'])
    ->prefix('candidate')
    ->name('candidate.')
    ->group(function (): void {
        Route::get('/applications/create', [CandidateRegistrationController::class, 'create'])
            ->name('applications.create');
        Route::post('/applications', [CandidateRegistrationController::class, 'store'])
            ->name('applications.store');
        Route::post('/applications/{candidacy}/cancel', CandidacyCancellationController::class)
            ->name('applications.cancel');
        Route::get('/applications/{candidacy}/photo', [CandidatePhotoController::class, 'show'])
            ->name('applications.photo');
        Route::get('/applications/{candidacy}/documents/{document}', [CandidateDocumentController::class, 'download'])
            ->name('applications.documents.download');

        Route::get('/profile', [CandidateProfileController::class, 'show'])
            ->name('profile.show');
        Route::patch('/profile', [CandidateProfileController::class, 'update'])
            ->name('profile.update');

        Route::get('/payments/{payment}', [CandidatePaymentController::class, 'show'])
            ->whereNumber('payment')
            ->name('payments.show');
    });
