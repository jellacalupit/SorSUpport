<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Check the current password without changing it.
     */
    public function checkCurrent(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->string('current_password'), $request->user()->password)) {
            return response()->json([
                'message' => 'Incorrect password. Try again.',
            ], 422);
        }

        return response()->json(['valid' => true]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [
            'current_password.current_password' => 'Incorrect password. Try again.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        AuditLog::activity('password_changed', $request->user()->id, 'Changed account password.');

        if ($request->user()->isStudent()) {
            return redirect()->route('student.dashboard');
        }

        if ($request->user()->isRecipient()) {
            return redirect()->route('recipient.dashboard');
        }

        return back()->with('status', 'password-updated');
    }
}
