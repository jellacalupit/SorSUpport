<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\Rule;

class ForcePasswordController extends Controller
{
    /**
     * Show the change password page.
     */
    public function show()
    {
        return view('auth.set-password');
    }

    /**
     * Save the new password.
     */
    public function update(Request $request)
    {
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                'min:8',
                Rule::notIn([(string) Auth::user()->username]),
            ],
        ], [
            'password.not_in' => 'Your new password cannot be your ID.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        AuditLog::activity('password_changed', $user->id, 'Changed password during initial account setup.');

        // Redirect based on role
        return match ($user->role) {

            User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard'),

            User::ROLE_STUDENT => redirect()->route('student.dashboard'),

            User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard'),

            default => redirect('/'),
        };
    }
}