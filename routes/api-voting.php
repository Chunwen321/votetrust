<?php

use App\Http\Controllers\Api\VotingApiController;
use App\Http\Middleware\ValidateVotingApiRequest;
use Illuminate\Support\Facades\Route;

// PIC: Goh Chun Wen - Voting Process module REST provider routes.
Route::middleware(['throttle:api', ValidateVotingApiRequest::class])->prefix('v1')->group(function (): void {
    Route::get('/votes/status', [VotingApiController::class, 'status'])->name('api.votes.status');
    Route::get('/elections/{election}/tally', [VotingApiController::class, 'tally'])
        ->whereNumber('election')
        ->name('api.votes.tally');
});
