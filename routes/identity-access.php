<?php

use App\Http\Controllers\Auth\AccountSecurityController;
use App\Http\Controllers\Auth\LocalVerificationMailboxController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Middleware\RequireActiveAccountSession;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/signup', [RegistrationController::class, 'show'])->name('auth.register');
    Route::post('/auth/verification/request', [RegistrationController::class, 'requestVerification'])
        ->name('auth.verification.request');
    Route::post('/auth/verification/verify', [RegistrationController::class, 'verify'])
        ->name('auth.verification.verify');
    Route::post('/signup', [RegistrationController::class, 'store'])
        ->name('auth.register.store');

    Route::get('/signin', [SessionController::class, 'show'])->name('auth.sign-in');
    Route::post('/signin', [SessionController::class, 'store'])->name('auth.sign-in.store');

    Route::get('/forgot', [RecoveryController::class, 'showRequest'])->name('auth.recovery.request');
    Route::post('/forgot', [RecoveryController::class, 'request'])
        ->name('auth.recovery.request.store');
    Route::get('/local/verification-mailbox/recovery', [
        LocalVerificationMailboxController::class,
        'recovery',
    ])->name('local.verification-mailbox.recovery');
    Route::get('/recover/{token}', [RecoveryController::class, 'showRedeem'])
        ->where('token', '[A-Za-z0-9]{32,128}')
        ->name('auth.recovery.redeem');
    Route::post('/recover/{token}', [RecoveryController::class, 'redeem'])
        ->where('token', '[A-Za-z0-9]{32,128}')
        ->name('auth.recovery.redeem.store');

    Route::middleware(RequireActiveAccountSession::class)->group(function (): void {
        Route::get('/account', [AccountSecurityController::class, 'show'])->name('account.home');
        Route::post('/account/password', [AccountSecurityController::class, 'updatePassword'])
            ->name('account.password.update');
    });
    Route::post('/signout', [SessionController::class, 'destroy'])
        ->middleware('auth')
        ->name('auth.sign-out');
});
