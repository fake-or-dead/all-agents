<?php

use App\Http\Controllers\Member\MemberCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/member/{tab?}', [MemberCenterController::class, 'show'])
        ->where('tab', 'profile|applications|training|password')
        ->name('member.home');
    Route::put('/member/profile', [MemberCenterController::class, 'updateProfile'])
        ->name('member.profile.update');
    Route::put('/member/address', [MemberCenterController::class, 'updateAddress'])
        ->name('member.address.update');
    Route::post('/member/training', [MemberCenterController::class, 'addTraining'])
        ->name('member.training.add');
    Route::put('/member/training/{trainingId}', [MemberCenterController::class, 'updateTraining'])
        ->whereUuid('trainingId')
        ->name('member.training.update');
});
