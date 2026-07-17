<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\AccountsImport;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\User as AppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class AccountManagementController extends Controller
{
    /**
     * Display all accounts.
     */
    public function index(): View
    {
        $users = AppUser::orderBy('name')->get();

        return view('admin.accounts.index', compact('users'));
    }

    /**
     * Show create account form.
     */
    public function create(): View
    {
        return view('admin.accounts.create');
    }

    /**
     * Store a new account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:student,recipient',
        ]);

        $username = null;

        if ($validated['role'] === AppUser::ROLE_STUDENT) {
            $username = $request->student_id;
        }

        if ($validated['role'] === AppUser::ROLE_RECIPIENT) {
            $username = $request->staff_id;
        }

        $user = AppUser::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'password' => Hash::make('Welcome@123'),
            'must_change_password' => true,
            'role' => $validated['role'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($validated['role'] === AppUser::ROLE_STUDENT) {

            Student::create([
                'user_id' => $user->id,
                'student_id' => $request->student_id,
                'department' => $request->department,
                'course' => $request->course,
                'year_level' => $request->year_level,
                'block' => $request->block,
            ]);

        }

        if ($validated['role'] === AppUser::ROLE_RECIPIENT) {

            Recipient::create([
                'user_id' => $user->id,
                'staff_id' => $request->staff_id,
                'department' => $request->recipient_department,
                'designation' => $request->designation,
            ]);

        }

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', 'Account created successfully. Initial password: Welcome@123');
    }

    /**
     * Show edit account form.
     */
    public function edit(AppUser $user)
    {
        $user->load([
            'student',
            'recipient',
        ]);

        return view('admin.accounts.edit', compact('user'));
    }

    /**
     * Update an account.
     */
    public function update(Request $request, AppUser $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->role === AppUser::ROLE_STUDENT && $user->student) {

            $user->update([
                'username' => $request->student_id,
            ]);

            $user->student->update([
                'student_id' => $request->student_id,
                'department' => $request->department,
                'course' => $request->course,
                'year_level' => $request->year_level,
                'block' => $request->block,
            ]);

        }

        if ($user->role === AppUser::ROLE_RECIPIENT && $user->recipient) {

            $user->update([
                'username' => $request->staff_id,
            ]);

            $user->recipient->update([
                'staff_id' => $request->staff_id,
                'department' => $request->recipient_department,
                'designation' => $request->designation,
            ]);

        }

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Deactivate an account.
     */
    public function deactivate(AppUser $user)
    {
        $user->update([
            'is_active' => false,
        ]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', 'Account deactivated successfully.');
    }

    /**
     * Reactivate an account.
     */
    public function reactivate(AppUser $user)
    {
        $user->update([
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', 'Account reactivated successfully.');
    }

    /**
     * Show upload form.
     */
    public function showUploadForm()
    {
        return view('admin.accounts.upload');
    }

    /**
     * Import student accounts.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,xlsx,xls',
        ]);

        $import = new AccountsImport();

        Excel::import($import, $request->file('file'));

        $summary = $import->summary();

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', sprintf(
                'Accounts imported. Created: %d, Updated: %d, Deactivated: %d.',
                $summary['created'],
                $summary['updated'],
                $summary['deactivated']
            ));
    }
}