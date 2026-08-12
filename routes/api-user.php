<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

use App\Http\Controllers\Api\DiditWebhookController;
use App\Http\Controllers\Api\PrivateVoterDirectoryController;
use App\Http\Controllers\Api\PrivateVoterEnrollmentController;
use App\Http\Controllers\Api\PrivateVoterSelectionController;
use App\Http\Controllers\Api\VoterEligibilityController;
use App\Http\Middleware\ValidateUserApiRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(ValidateUserApiRequest::class)
    ->get('/v1/voters/eligibility', VoterEligibilityController::class)
    ->name('api.voters.eligibility');

Route::middleware(ValidateUserApiRequest::class)
    ->post('/v1/voters/private-enrollments', PrivateVoterEnrollmentController::class)
    ->name('api.voters.private-enrollments');

Route::middleware(ValidateUserApiRequest::class)
    ->get('/v1/voters/private-directory', PrivateVoterDirectoryController::class)
    ->name('api.voters.private-directory');

Route::middleware(ValidateUserApiRequest::class)
    ->patch('/v1/voters/private-enrollments/selection', PrivateVoterSelectionController::class)
    ->name('api.voters.private-enrollments.selection');

// Public HTTPS callback from Didit. It has its own HMAC/timestamp verification and must not use user-service API credentials.
Route::post('/v1/webhooks/didit', DiditWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.webhooks.didit');
