<?php

use App\Http\Controllers\Public\ContentPageController;
use App\Http\Controllers\Public\CourseCatalogController;
use App\Http\Controllers\Public\CourseDetailController;
use App\Http\Controllers\Public\DocumentPlaceholderController;
use Illuminate\Support\Facades\Route;

Route::get('/', CourseCatalogController::class)->name('course-catalog.home');
Route::get('/course', CourseCatalogController::class)->name('course-catalog.index');
Route::get('/course/detail/{courseCode}', [CourseDetailController::class, 'show'])
    ->where('courseCode', '[A-Za-z0-9-]+')
    ->name('course-catalog.detail');
Route::post('/course/detail/{courseCode}/eligibility', [CourseDetailController::class, 'assess'])
    ->where('courseCode', '[A-Za-z0-9-]+')
    ->name('course-catalog.eligibility');
Route::get('/suggest', [ContentPageController::class, 'suggest'])->name('public.suggest');
Route::get('/applicant-qualifications', [ContentPageController::class, 'qualifications'])
    ->name('public.qualifications');
Route::get('/about', [ContentPageController::class, 'about'])->name('public.about');
Route::get('/documents/{documentKey}', DocumentPlaceholderController::class)
    ->where('documentKey', '[a-z0-9-]+')
    ->name('public.document-placeholder');
