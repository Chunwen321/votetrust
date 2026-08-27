<?php

// PIC: Low Wen Kai - Election Management Module

use App\Http\Controllers\AdminElectionController;
use App\Http\Controllers\ElectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.account'])->group(function (): void {
    Route::get('/elections', [ElectionController::class, 'index'])->name('elections.index');
    Route::get('/elections/{election}', [ElectionController::class, 'show'])->whereNumber('election')->name('elections.show');
});

Route::middleware(['auth', 'active.account', 'verified.profile'])->group(function (): void {
    Route::get('/elections/create', [ElectionController::class, 'create'])->name('elections.create');
    Route::post('/elections', [ElectionController::class, 'store'])->name('elections.store');
    Route::get('/elections/private-voters/template', [ElectionController::class, 'downloadPrivateVoterTemplate'])->name('elections.private-voters.template');
    Route::get('/elections/private-candidates/template', [ElectionController::class, 'downloadPrivateCandidateTemplate'])->name('elections.private-candidates.template');
    Route::get('/elections/{election}/edit', [ElectionController::class, 'edit'])->whereNumber('election')->name('elections.edit');
    Route::put('/elections/{election}', [ElectionController::class, 'update'])->whereNumber('election')->name('elections.update');
    Route::get('/elections/{election}/private-voters', [ElectionController::class, 'privateVoterDirectory'])->whereNumber('election')->name('elections.private-voters.directory');
    Route::post('/elections/{election}/private-voters', [ElectionController::class, 'importPrivateVoters'])->whereNumber('election')->name('elections.private-voters.import');
    Route::patch('/elections/{election}/private-voters/{user}', [ElectionController::class, 'updatePrivateVoterSelection'])->whereNumber(['election', 'user'])->name('elections.private-voters.selection');
    Route::get('/elections/{election}/private-candidates', [ElectionController::class, 'privateCandidateDirectory'])->whereNumber('election')->name('elections.private-candidates.directory');
    Route::post('/elections/{election}/private-candidates', [ElectionController::class, 'importPrivateCandidates'])->whereNumber('election')->name('elections.private-candidates.import');
    Route::patch('/elections/{election}/private-candidates/{user}', [ElectionController::class, 'updatePrivateCandidateSelection'])->whereNumber(['election', 'user'])->name('elections.private-candidates.selection');
    Route::post('/elections/{election}/candidates/assign', [ElectionController::class, 'assignPrivateCandidate'])->whereNumber('election')->name('elections.candidates.assign');
    Route::post('/elections/{election}/candidates', [ElectionController::class, 'reviewCandidates'])->whereNumber('election')->name('elections.candidates.review');
    Route::post('/elections/{election}/transition', [ElectionController::class, 'transition'])->whereNumber('election')->name('elections.transition');
});

Route::middleware(['auth', 'active.account', 'verified.profile', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/elections', [AdminElectionController::class, 'index'])->name('elections.index');
    Route::get('/elections/{election}', [AdminElectionController::class, 'show'])->whereNumber('election')->name('elections.show');
    Route::post('/elections/{election}/transition', [AdminElectionController::class, 'transition'])->whereNumber('election')->name('elections.transition');
});
