<?php

// PIC: Risky Puah Kaho - Payment Module — administrator diagnostic pages and the IDontPay99 checkout.

use App\Http\Controllers\Admin\PaymentHealthController;
use App\Http\Controllers\Admin\PaymentReviewController;
use App\Http\Controllers\Admin\PaymentSettlementController;
use App\Http\Controllers\PaymentCheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.account', 'verified.profile', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/payments', [PaymentReviewController::class, 'index'])
            ->name('payments.index');
        Route::post('/payments/elections/{election}/verify', [PaymentReviewController::class, 'verify'])
            ->whereNumber('election')
            ->name('payments.verify');
        Route::get('/payments/health', [PaymentHealthController::class, 'show'])
            ->name('payments.health');
        Route::post('/payments/health/test', [PaymentHealthController::class, 'runTest'])
            ->name('payments.health.test');
        Route::post('/payments/{payment}/settle', [PaymentSettlementController::class, 'update'])
            ->whereNumber('payment')
            ->name('payments.settle');
    });

Route::middleware(['auth', 'active.account'])->group(function (): void {
    Route::get('/payments/{payment}/checkout', [PaymentCheckoutController::class, 'show'])
        ->whereNumber('payment')
        ->name('payments.checkout.show');
    Route::post('/payments/{payment}/checkout', [PaymentCheckoutController::class, 'charge'])
        ->whereNumber('payment')
        ->name('payments.checkout.charge');
    Route::get('/payments/elections/{election}/pay', [PaymentCheckoutController::class, 'payForElection'])
        ->whereNumber('election')
        ->name('payments.checkout.election');
    Route::get('/payments/{payment}/receipt', [PaymentCheckoutController::class, 'receipt'])
        ->whereNumber('payment')
        ->name('payments.checkout.receipt');
});
