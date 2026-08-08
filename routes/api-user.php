<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

use App\Http\Controllers\Api\VoterEligibilityController;
use App\Http\Middleware\ValidateUserApiRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(ValidateUserApiRequest::class)
    ->get('/v1/voters/eligibility', VoterEligibilityController::class)
    ->name('api.voters.eligibility');
