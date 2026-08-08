<?php

// PIC: Low Wen Kai - Election Management Module

use App\Http\Controllers\AdminElectionController;
use App\Http\Controllers\ElectionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/elections', [ElectionController::class, 'index'])->name('elections.index');
    Route::get('/elections/create', [ElectionController::class, 'create'])->name('elections.create');
    Route::post('/elections', [ElectionController::class, 'store'])->name('elections.store');
    Route::get('/elections/{election}', [ElectionController::class, 'show'])->whereNumber('election')->name('elections.show');
    Route::get('/elections/{election}/edit', [ElectionController::class, 'edit'])->whereNumber('election')->name('elections.edit');
    Route::put('/elections/{election}', [ElectionController::class, 'update'])->whereNumber('election')->name('elections.update');
    Route::post('/elections/{election}/candidates', [ElectionController::class, 'reviewCandidates'])->whereNumber('election')->name('elections.candidates.review');
    Route::post('/elections/{election}/transition', [ElectionController::class, 'transition'])->whereNumber('election')->name('elections.transition');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/elections', [AdminElectionController::class, 'index'])->name('elections.index');
    Route::get('/elections/{election}', [AdminElectionController::class, 'show'])->whereNumber('election')->name('elections.show');
    Route::post('/elections/{election}/transition', [AdminElectionController::class, 'transition'])->whereNumber('election')->name('elections.transition');
});
