<?php

// PIC: Low Wen Kai - Election Management Module

use App\Http\Controllers\Api\AdminElectionApprovalController;
use App\Http\Controllers\Api\ElectionAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('v1')->group(function (): void {
    Route::get('/elections/available', [ElectionAvailabilityController::class, 'index'])
        ->name('api.elections.available');
    Route::get('/elections/{election}/availability', [ElectionAvailabilityController::class, 'show'])
        ->whereNumber('election')
        ->name('api.elections.availability');
    Route::post('/elections/{election}/approval', [AdminElectionApprovalController::class, 'decide'])
        ->whereNumber('election')
        ->name('api.elections.approval');
});
