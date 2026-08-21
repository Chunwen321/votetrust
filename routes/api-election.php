<?php

// PIC: Low Wen Kai - Election Management Module

use App\Http\Controllers\Api\AdminElectionApprovalController;
use App\Http\Controllers\Api\ElectionAvailabilityController;
use App\Http\Controllers\Api\ElectionSummaryController;
use App\Http\Middleware\ValidateElectionApiRequest;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('v1')->group(function (): void {
    Route::middleware(ValidateElectionApiRequest::class)->group(function (): void {
        Route::get('/elections/available', [ElectionAvailabilityController::class, 'index'])
            ->name('api.elections.available');
        Route::get('/elections/voter-catalog', [ElectionAvailabilityController::class, 'catalog'])
            ->name('api.elections.voter-catalog');
        Route::get('/elections/{election}/availability', [ElectionAvailabilityController::class, 'show'])
            ->whereNumber('election')
            ->name('api.elections.availability');
    });
    Route::get('/elections/summaries', [ElectionSummaryController::class, 'summaries'])
        ->name('api.elections.summaries');
    Route::get('/elections/organizer/{user}', [ElectionSummaryController::class, 'organizer'])
        ->whereNumber('user')
        ->name('api.elections.organizer');
    Route::get('/elections/admin/overview', [ElectionSummaryController::class, 'adminOverview'])
        ->name('api.elections.admin-overview');
    Route::post('/elections/{election}/approval', [AdminElectionApprovalController::class, 'decide'])
        ->whereNumber('election')
        ->name('api.elections.approval');
});
