<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Recipient\RecipientDashboardController;
use App\Http\Controllers\Admin\AccountManagementController;
use App\Http\Controllers\Auth\ForcePasswordController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'force.password', 'role:sds_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/accounts', [AccountManagementController::class, 'index'])
            ->name('accounts.index');

        Route::get('/accounts/create', [AccountManagementController::class, 'create'])
            ->name('accounts.create');

        Route::post('/accounts', [AccountManagementController::class, 'store'])
            ->name('accounts.store');

        Route::get('/accounts/{user}/edit', [AccountManagementController::class, 'edit'])
            ->name('accounts.edit');

        Route::put('/accounts/{user}', [AccountManagementController::class, 'update'])
            ->name('accounts.update');

        Route::patch('/accounts/{user}/deactivate', [AccountManagementController::class, 'deactivate'])
            ->name('accounts.deactivate');

        Route::get('/accounts/upload', [AccountManagementController::class, 'showUploadForm'])
            ->name('accounts.upload');

        Route::post('/accounts/upload', [AccountManagementController::class, 'upload'])
            ->name('accounts.upload.store');
    });

Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])
    ->middleware(['auth', 'force.password', 'role:student'])
    ->name('student.dashboard');

Route::get('/recipient/dashboard', [RecipientDashboardController::class, 'index'])
    ->middleware(['auth', 'force.password', 'role:recipient'])
    ->name('recipient.dashboard');

Route::middleware(['auth', 'force.password'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Force Password Change
    |--------------------------------------------------------------------------
    */

    Route::get('/force-password', [ForcePasswordController::class, 'show'])
        ->name('password.force');

    Route::post('/force-password', [ForcePasswordController::class, 'update'])
        ->name('password.force.update');

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';