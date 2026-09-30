<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\ComplaintController;
use App\Http\Controllers\Recipient\RecipientDashboardController;
use App\Http\Controllers\Admin\AccountManagementController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Auth\ForcePasswordController;
use App\Http\Controllers\Admin\ComplaintCategoryController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\AdminComplaintController;
use App\Http\Controllers\Admin\AdminTicketReviewController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Recipient\NotificationController as RecipientNotificationController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;



Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return match (request()->user()->role) {
        \App\Models\User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard'),
        \App\Models\User::ROLE_STUDENT => redirect()->route('student.dashboard'),
        \App\Models\User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard'),
        default => redirect()->route('login'),
    };
})->middleware(['auth'])->name('dashboard');

Route::middleware(app()->environment(['local', 'testing']) ? [] : ['auth', 'force.password'])->group(function () {
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/prototype/student', function () {
        $user = \App\Models\User::query()
            ->where('role', \App\Models\User::ROLE_STUDENT)
            ->first();

        if (! $user) {
            $user = \App\Models\User::create([
                'name' => 'Prototype Student',
                'username' => 'prototype.student',
                'email' => 'prototype.student@sorsu.edu.ph',
                'password' => 'password',
                'must_change_password' => false,
                'role' => \App\Models\User::ROLE_STUDENT,
                'is_active' => true,
            ]);

            $user->student()->create([
                'student_id' => '23581616',
                'department' => 'Student Development Services',
                'course' => 'Prototype Account',
                'year_level' => '4',
                'block' => 'A',
            ]);
        }

        $user->name = 'Jella Mae Guelas Calupit';
        $user->email = 'jella.calupit@sorsu.edu.ph';
        $user->password = \Illuminate\Support\Facades\Hash::make('password');
        $user->email_verified_at = now();
        $user->save();
        $user->student()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'student_id' => '23175866',
                'department' => 'CICT',
                'course' => 'BSIT',
                'year_level' => '4',
                'block' => '5',
            ]
        );

        \Illuminate\Support\Facades\Auth::login($user);

        return redirect()->route('student.dashboard');
    })->name('prototype.student.dashboard');

    Route::get('/prototype/admin', function () {
        $user = \App\Models\User::query()
            ->where('role', \App\Models\User::ROLE_SDS_ADMIN)
            ->first();

        if (! $user) {
            $user = \App\Models\User::create([
                'name' => 'Prototype SDS Administrator',
                'username' => 'prototype.admin',
                'email' => 'prototype.admin@sorsu.edu.ph',
                'password' => 'password',
                'must_change_password' => false,
                'role' => \App\Models\User::ROLE_SDS_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        \Illuminate\Support\Facades\Auth::login($user);

        return redirect()->route('admin.dashboard');
    })->name('prototype.admin.dashboard');

    Route::get('/prototype/recipient', function () {
        $user = \App\Models\User::query()
            ->where('role', \App\Models\User::ROLE_RECIPIENT)
            ->first();

        if (! $user) {
            $user = \App\Models\User::create([
                'name' => 'Agatha Nicole Varias Carungcong',
                'username' => 'prototype.recipient',
                'email' => 'agathanicolecarungcong@sorsu.edu.ph',
                'password' => 'password',
                'must_change_password' => false,
                'role' => \App\Models\User::ROLE_RECIPIENT,
                'is_active' => true,
            ]);

            $user->recipient()->create([
                'staff_id' => '2465',
                'department' => 'CICT',
                'designation' => 'Program Chair',
            ]);
        }

        $user->update([
            'name' => 'Agatha Nicole Varias Carungcong',
            'email' => 'agathanicolecarungcong@sorsu.edu.ph',
        ]);
        $user->recipient()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'staff_id' => '2465',
                'department' => 'CICT',
                'designation' => 'Program Chair',
            ]
        );

        \Illuminate\Support\Facades\Auth::login($user);

        return redirect()->route('recipient.dashboard');
    })->name('prototype.recipient.dashboard');
}

Route::middleware(['auth', 'active.user', 'force.password', 'role:sds_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/accounts', [AccountManagementController::class, 'index'])
            ->name('accounts.index');

        Route::post('/accounts', [AccountManagementController::class, 'store'])
            ->name('accounts.store');

        Route::get('/accounts/{user}/edit', function (\App\Models\User $user) {
            return redirect()->route('admin.accounts.index');
        })->name('accounts.edit');

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

        Route::get('/accounts/upload/errors', [AccountManagementController::class, 'downloadUploadErrors'])
            ->name('accounts.upload.errors');

        Route::post('/categories', [ComplaintCategoryController::class, 'store'])
            ->name('categories.store');

        Route::put('/categories/{category}', [ComplaintCategoryController::class, 'update'])
            ->name('categories.update');

        Route::patch('/categories/{category}/toggle-status', [ComplaintCategoryController::class, 'toggleStatus'])
            ->name('categories.toggle-status');
        Route::delete('/categories/{category}', [ComplaintCategoryController::class, 'destroy'])
            ->name('categories.destroy');

        Route::post('/departments', [DepartmentController::class, 'store'])
            ->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])
            ->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
            ->name('departments.destroy');

        // Complaint Queue (identified)
        Route::get('/complaints', [AdminComplaintController::class, 'index'])
            ->name('complaints.index');

        Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])
            ->whereNumber('complaint')
            ->name('complaints.show');

        Route::post('/complaints/{complaint}/reply', [AdminComplaintController::class, 'storeReply'])
            ->name('complaints.reply');

        // Anonymous informational complaints (no ticket tracking)
        // Ticket review and classification (Module 3)
        Route::get('/tickets/review', [AdminTicketReviewController::class, 'index'])
            ->name('tickets.review.index');
        Route::post('/tickets/{ticket}/read', [AdminTicketReviewController::class, 'markRead'])
            ->name('tickets.read');

        Route::get('/tickets/my', [AdminTicketReviewController::class, 'myTickets'])
            ->name('tickets.my');

        Route::post('/tickets/{ticket}/reject', [AdminTicketReviewController::class, 'reject'])
            ->name('tickets.reject');

        Route::post('/tickets/{ticket}/classify', [AdminTicketReviewController::class, 'classify'])
            ->name('tickets.classify');

        Route::post('/tickets/{ticket}/forward', [AdminTicketReviewController::class, 'forward'])
            ->name('tickets.forward');

        Route::post('/tickets/{ticket}/retain-informational', [AdminTicketReviewController::class, 'retainInformational'])
            ->name('tickets.retain-informational');

        Route::post('/tickets/{ticket}/forward-informational-close', [AdminTicketReviewController::class, 'forwardInformationalAndClose'])
            ->name('tickets.forward-informational-close');

        Route::post('/tickets/{ticket}/assign', [AdminTicketReviewController::class, 'assign'])
            ->name('tickets.assign');
        Route::post('/tickets/{ticket}/acknowledge', [AdminTicketReviewController::class, 'acknowledge'])
            ->name('tickets.acknowledge');

        Route::post('/tickets/{ticket}/resolve', [AdminTicketReviewController::class, 'resolve'])
            ->name('tickets.resolve');

        Route::post('/tickets/{ticket}/not-yet-resolved', [AdminTicketReviewController::class, 'notYetResolved'])
            ->name('tickets.not-yet-resolved');

        Route::post('/tickets/{ticket}/close', [AdminTicketReviewController::class, 'close'])
            ->name('tickets.close');

        Route::post('/tickets/{ticket}/escalate', [AdminTicketReviewController::class, 'escalate'])
            ->name('tickets.escalate');

        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->name('analytics.index');

        Route::get('/audit', function (\Illuminate\Http\Request $request) {
            $query = \App\Models\AuditLog::query()->with(['performer', 'ticket.complaint'])->latest();

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($audit) use ($search) {
                    $audit->where('action', 'like', '%' . $search . '%')
                        ->orWhere('details', 'like', '%' . $search . '%')
                        ->orWhereHas('performer', fn ($performer) => $performer->where('name', 'like', '%' . $search . '%'))
                        ->orWhere('ticket_id', 'like', '%' . $search . '%')
                        ->orWhereHas('ticket.complaint', fn ($complaint) => $complaint->where('reference_number', 'like', '%' . $search . '%'));
                });
            }

            if ($request->filled('account_type')) {
                if ($request->input('account_type') === 'system') {
                    $query->whereNull('performed_by');
                } else {
                    $query->whereHas('performer', fn ($performer) => $performer->where('role', $request->input('account_type')));
                }
            }

            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->input('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->input('to'));
            }

            $auditLogs = $query->paginate(15)->appends($request->query());
            $actions = \App\Models\AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

            return view('admin.audit', compact('auditLogs', 'actions'));
        })->name('audit');

        Route::get('/settings', function () {
            return view('admin.settings', [
                'categories' => \App\Models\ComplaintCategory::query()
                    ->with(['escalationHierarchies.recipient.user', 'suggestedRecipients.user'])
                    ->orderBy('name')
                    ->get(),
                'recipients' => \App\Models\Recipient::query()
                    ->active()
                    ->with('user')
                    ->orderBy('department')
                    ->get(),
                'departments' => \App\Models\Department::query()
                    ->with(['positions', 'courses'])
                    ->orderBy('name')
                    ->get(),
            ]);
        })->name('settings');

        Route::get('/analytics/export/pdf', [AnalyticsController::class, 'exportPdf'])
            ->name('analytics.export.pdf');

        Route::get('/analytics/export/excel', [AnalyticsController::class, 'exportExcel'])
            ->name('analytics.export.excel');
    });


Route::middleware(['auth', 'active.user', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {

        Route::get('/dashboard', [StudentDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications');

        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        Route::post('/notifications/delete-selected', [NotificationController::class, 'deleteSelected'])
            ->name('notifications.delete-selected');

        Route::post('/notifications/{notificationId}/open', [NotificationController::class, 'open'])
            ->name('notifications.open');

        Route::get('/profile', function () {
            return view('student.profile', [
                'user' => request()->user(),
                'student' => request()->user()->student,
            ]);
        })->name('profile');

        Route::post('/profile/photo', [StudentProfileController::class, 'updatePhoto'])
            ->name('profile.photo');

        Route::get('/complaints', [ComplaintController::class, 'index'])
            ->name('complaints.index');

        Route::get('/complaints/create', [ComplaintController::class, 'create'])
            ->name('complaints.create');

        Route::post('/complaints', [ComplaintController::class, 'store'])
            ->name('complaints.store');

        Route::get('/complaints/{complaint}/submitted', [ComplaintController::class, 'submitted'])
            ->name('complaints.submitted');

        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])
            ->name('complaints.show');

        Route::post('/complaints/{complaint}/reply', [ComplaintController::class, 'storeReply'])
            ->name('complaints.reply');
    });

Route::middleware(['auth', 'active.user', 'role:recipient'])
    ->prefix('recipient')
    ->name('recipient.')
    ->group(function () {

        Route::get('/dashboard', [RecipientDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/notifications', [RecipientNotificationController::class, 'index'])
            ->name('notifications');

        Route::post('/notifications/read-all', [RecipientNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        Route::post('/notifications/delete-selected', [RecipientNotificationController::class, 'deleteSelected'])
            ->name('notifications.delete-selected');

        Route::post('/notifications/{notificationId}/open', [RecipientNotificationController::class, 'open'])
            ->name('notifications.open');

        Route::get('/profile', function () {
            return view('recipient.profile', [
                'user' => request()->user(),
                'recipient' => request()->user()->recipient,
            ]);
        })->name('profile');

        Route::post('/profile/photo', [StudentProfileController::class, 'updatePhoto'])
            ->name('profile.photo');

        Route::get('/tickets', [\App\Http\Controllers\Recipient\ComplaintController::class, 'index'])
            ->name('tickets.index');

        Route::get('/tickets/{complaint}', [\App\Http\Controllers\Recipient\ComplaintController::class, 'show'])
            ->name('tickets.show');

        // Complaint management
        Route::get('/complaints', [\App\Http\Controllers\Recipient\ComplaintController::class, 'index'])
            ->name('complaints.index');

        Route::get('/complaints/{complaint}', [\App\Http\Controllers\Recipient\ComplaintController::class, 'show'])
            ->name('complaints.show');

        Route::post('/complaints/{complaint}/reply', [\App\Http\Controllers\Recipient\ComplaintController::class, 'storeReply'])
            ->name('complaints.reply');

        Route::patch('/complaints/{complaint}/status', [\App\Http\Controllers\Recipient\ComplaintController::class, 'updateStatus'])
            ->name('complaints.update-status');
        Route::post('/complaints/{complaint}/acknowledge', [\App\Http\Controllers\Recipient\ComplaintController::class, 'acknowledge'])
            ->name('complaints.acknowledge');
    });

Route::middleware(['auth', 'active.user', 'force.password'])->group(function () {

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

    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->name('profile.photo');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';