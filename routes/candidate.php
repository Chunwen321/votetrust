<?php

use App\Http\Controllers\CandidacyCancellationController;
use App\Http\Controllers\CandidateApplicationController;
use App\Http\Controllers\CandidateDocumentController;
use App\Http\Controllers\CandidatePaymentController;
use App\Http\Controllers\CandidatePhotoController;
use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CandidateRegistrationController;
use Illuminate\Support\Facades\Route;

// PIC: Wong Shun Bin — Candidate Registration module routes.
Route::middleware(['auth', 'active.account', 'verified.profile'])
    ->prefix('candidate')
    ->name('candidate.')
    ->group(function (): void {
        Route::get('/applications', [CandidateApplicationController::class, 'index'])
            ->name('applications.index');
        Route::get('/applications/{candidacy}', [CandidateApplicationController::class, 'show'])
            ->whereNumber('candidacy')
            ->name('applications.show');
        Route::get('/elections/{election}/applications/create', [CandidateRegistrationController::class, 'create'])
            ->whereNumber('election')
            ->name('applications.create');
        Route::post('/elections/{election}/applications', [CandidateRegistrationController::class, 'store'])
            ->whereNumber('election')
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
