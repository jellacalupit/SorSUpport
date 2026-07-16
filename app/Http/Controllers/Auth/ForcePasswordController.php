<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ForcePasswordController extends Controller
{
    /**
     * Show the change password page.
     */
    public function show()
    {
        return view('auth.force-password');
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
            ],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        // Redirect based on role
        return match ($user->role) {

            User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard'),

            User::ROLE_STUDENT => redirect()->route('student.dashboard'),

            User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard'),

            default => redirect('/'),
        };
    }
}