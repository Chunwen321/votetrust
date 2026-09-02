<?php

use App\Http\Controllers\VotingController;
use Illuminate\Support\Facades\Route;

// PIC: Goh Chun Wen - Voting Process module routes.
Route::get('/voting/elections/{election}/results', [VotingController::class, 'results'])
    ->whereNumber('election')
    ->name('voting.results');

Route::middleware(['auth', 'active.account', 'verified.profile'])->prefix('voting')->name('voting.')->group(function (): void {
    // Keep old bookmarks working without maintaining a second election catalog.
    Route::redirect('/', '/elections')->name('index');
    Route::get('/elections/{election}', [VotingController::class, 'ballot'])
        ->whereNumber('election')
        ->name('ballot');
    Route::post('/elections/{election}/votes', [VotingController::class, 'cast'])
        ->middleware('throttle:voting.cast')
        ->whereNumber('election')
        ->name('cast');
    Route::get('/receipts/{vote}', [VotingController::class, 'receipt'])
        ->whereNumber('vote')
        ->name('receipt');
});
