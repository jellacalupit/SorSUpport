<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use App\Models\Recipient;
use App\Imports\AccountsImport;
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
        $users = User::orderBy('name')->get();

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
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role'  => 'required|in:student,recipient',
        ]);

        // Determine username
        $username = null;

        if ($validated['role'] === User::ROLE_STUDENT) {
            $username = $request->student_id;
        }

        if ($validated['role'] === User::ROLE_RECIPIENT) {
            $username = $request->staff_id;
        }

        // Create user account
        $user = User::create([
            'name'                 => $validated['name'],
            'username'             => $username,
            'email'                => $validated['email'],
            'password'             => Hash::make('Welcome@123'),
            'must_change_password' => true,
            'role'                 => $validated['role'],
            'is_active'            => true,
        ]);

        // Student profile
        if ($validated['role'] === User::ROLE_STUDENT) {

            Student::create([
                'user_id'     => $user->id,
                'student_id'  => $request->student_id,
                'department'  => $request->department,
                'course'      => $request->course,
                'year_level'  => $request->year_level,
                'block'       => $request->block,
            ]);

        }

        // Recipient profile
        if ($validated['role'] === User::ROLE_RECIPIENT) {

            Recipient::create([
                'user_id'     => $user->id,
                'staff_id'    => $request->staff_id,
                'department'  => $request->recipient_department,
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
    public function edit(User $user)
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
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        // Update user
        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        // Student
        if ($user->role === User::ROLE_STUDENT && $user->student) {

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

        // Recipient
        if ($user->role === User::ROLE_RECIPIENT && $user->recipient) {

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
    public function deactivate(User $user)
    {
        $user->update([
            'is_active' => false,
        ]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', 'Account deactivated successfully.');
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

        Excel::import(new AccountsImport, $request->file('file'));

        return redirect()
            ->route('admin.accounts.index')
            ->with('success', 'Accounts imported successfully.');
    }
}