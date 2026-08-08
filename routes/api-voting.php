<?php

use App\Http\Controllers\Api\VotingApiController;
use Illuminate\Support\Facades\Route;

// PIC: Goh Chun Wen - Voting Process module REST provider routes.
Route::middleware('throttle:api')->prefix('v1')->group(function (): void {
    Route::get('/votes/status', [VotingApiController::class, 'status'])->name('api.votes.status');
    Route::get('/elections/{election}/tally', [VotingApiController::class, 'tally'])
        ->whereNumber('election')
        ->name('api.votes.tally');
});
