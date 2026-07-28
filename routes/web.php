<?php

use App\Http\Controllers\Health\LivenessController;
use App\Http\Controllers\Health\ReadinessController;
use App\Http\Controllers\PlatformProbeController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;

Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function (): void {
    Route::get('/health/live', LivenessController::class)->name('health.live');
    Route::get('/health/ready', ReadinessController::class)->name('health.ready');
});
Route::post('/platform/probes', [PlatformProbeController::class, 'store']);
Route::get('/platform/probes/{probeId}', [PlatformProbeController::class, 'show'])
    ->whereUuid('probeId');

Route::get('/_local/system-state', function () {
    return Inertia::render('SystemState', [
        'build' => [
            'version' => (string) config('platform.build.version'),
            'commit' => substr((string) config('platform.build.commit'), 0, 12),
        ],
    ]);
})->name('system-state');

require __DIR__.'/course-catalog.php';
require __DIR__.'/reference-data.php';
