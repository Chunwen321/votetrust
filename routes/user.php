<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ParticipantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VoterController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Low Wen Kai owns the canonical /elections route; /voter is the user-module view.
    Route::get('/voter', [VoterController::class, 'dashboard'])->name('voter.dashboard');

    Route::prefix('admin')->name('admin.')->middleware(EnsureAdmin::class)->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/elections/{election}/participants', [ParticipantController::class, 'show'])
            ->name('participants.show');
        Route::post('/elections/{election}/participants/voters', [ParticipantController::class, 'storeVoter'])
            ->name('participants.voters.store');
        Route::patch('/elections/{election}/participants/voters/{user}', [ParticipantController::class, 'updateVoter'])
            ->name('participants.voters.update');
        Route::post('/elections/{election}/participants/candidates', [ParticipantController::class, 'storeCandidate'])
            ->name('participants.candidates.store');
        Route::patch('/elections/{election}/participants/candidates/{user}', [ParticipantController::class, 'updateCandidate'])
            ->name('participants.candidates.update');
    });
});
