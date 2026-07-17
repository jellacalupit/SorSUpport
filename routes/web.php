<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\ComplaintController;
use App\Http\Controllers\Recipient\RecipientDashboardController;
use App\Http\Controllers\Admin\AccountManagementController;
use App\Http\Controllers\Auth\ForcePasswordController;
use App\Http\Controllers\Admin\ComplaintCategoryController;
use App\Http\Controllers\Admin\AdminComplaintController;
use App\Http\Controllers\Admin\AdminAnonymousComplaintController;
use App\Http\Controllers\Admin\AdminTicketReviewController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

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

        Route::patch('/accounts/{user}/reactivate', [AccountManagementController::class, 'reactivate'])
            ->name('accounts.reactivate');

        Route::get('/accounts/upload', [AccountManagementController::class, 'showUploadForm'])
            ->name('accounts.upload');

        Route::post('/accounts/upload', [AccountManagementController::class, 'upload'])
            ->name('accounts.upload.store');

        Route::get('/categories', [ComplaintCategoryController::class, 'index'])
            ->name('categories.index');

        Route::get('/categories/create', [ComplaintCategoryController::class, 'create'])
            ->name('categories.create');

        Route::post('/categories', [ComplaintCategoryController::class, 'store'])
            ->name('categories.store');

        Route::get('/categories/{category}/edit', [ComplaintCategoryController::class, 'edit'])
            ->name('categories.edit');

        Route::put('/categories/{category}', [ComplaintCategoryController::class, 'update'])
            ->name('categories.update');

        Route::patch('/categories/{category}/toggle-status', [ComplaintCategoryController::class, 'toggleStatus'])
            ->name('categories.toggle-status');

        // Complaint Queue (identified)
        Route::get('/complaints', [AdminComplaintController::class, 'index'])
            ->name('complaints.index');

        Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])
            ->name('complaints.show');

        Route::post('/complaints/{complaint}/reply', [AdminComplaintController::class, 'storeReply'])
            ->name('complaints.reply');

        // Anonymous informational complaints (no ticket tracking)
        Route::get('/complaints/anonymous', [AdminAnonymousComplaintController::class, 'index'])
            ->name('complaints.anonymous');

        Route::get('/complaints/anonymous/{complaint}', [AdminAnonymousComplaintController::class, 'show'])
            ->name('complaints.anonymous.show');

        // Ticket review and classification (Module 3)
        Route::get('/tickets/review', [AdminTicketReviewController::class, 'index'])
            ->name('tickets.review.index');

        Route::get('/tickets/review/{ticket}', [AdminTicketReviewController::class, 'show'])
            ->name('tickets.review.show');

        Route::post('/tickets/{ticket}/reject', [AdminTicketReviewController::class, 'reject'])
            ->name('tickets.reject');

        Route::post('/tickets/{ticket}/classify', [AdminTicketReviewController::class, 'classify'])
            ->name('tickets.classify');

        Route::post('/tickets/{ticket}/forward', [AdminTicketReviewController::class, 'forward'])
            ->name('tickets.forward');

        Route::post('/tickets/{ticket}/assign', [AdminTicketReviewController::class, 'assign'])
            ->name('tickets.assign');
    });


Route::middleware(['auth', 'force.password', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {

        Route::get('/dashboard', [StudentDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/complaints', [ComplaintController::class, 'index'])
            ->name('complaints.index');

        Route::get('/complaints/create', [ComplaintController::class, 'create'])
            ->name('complaints.create');

        Route::post('/complaints', [ComplaintController::class, 'store'])
            ->name('complaints.store');

        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])
            ->name('complaints.show');

        Route::post('/complaints/{complaint}/reply', [ComplaintController::class, 'storeReply'])
            ->name('complaints.reply');
    });

Route::middleware(['auth', 'force.password', 'role:recipient'])
    ->prefix('recipient')
    ->name('recipient.')
    ->group(function () {

        Route::get('/dashboard', [RecipientDashboardController::class, 'index'])
            ->name('dashboard');

        // Complaint management
        Route::get('/complaints', [\App\Http\Controllers\Recipient\ComplaintController::class, 'index'])
            ->name('complaints.index');

        Route::get('/complaints/{complaint}', [\App\Http\Controllers\Recipient\ComplaintController::class, 'show'])
            ->name('complaints.show');

        Route::post('/complaints/{complaint}/reply', [\App\Http\Controllers\Recipient\ComplaintController::class, 'storeReply'])
            ->name('complaints.reply');

        Route::patch('/complaints/{complaint}/status', [\App\Http\Controllers\Recipient\ComplaintController::class, 'updateStatus'])
            ->name('complaints.update-status');
    });

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