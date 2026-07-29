<?php

use App\Http\Controllers\Public\ReferenceDataController;
use Illuminate\Support\Facades\Route;

Route::get('/references/provinces', [ReferenceDataController::class, 'provinces'])
    ->name('reference.provinces');
Route::get('/select/amphoes', [ReferenceDataController::class, 'amphoes'])
    ->name('reference.amphoes');
Route::get('/select/tambons', [ReferenceDataController::class, 'tambons'])
    ->name('reference.tambons');
