<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Display the password reset email confirmation view.
     */
    public function sent(): View
    {
        return view('auth.password-email-sent');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
        ], [
            'username.required' => 'Student or Staff ID is required.',
        ]);

        $user = User::where('username', (string) $request->input('username'))->first();

        if (! $user || ! $user->email) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Incorrect ID. Try again.']);
        }

        $status = Password::sendResetLink(
            ['email' => $user->email]
        );

        return $status == Password::RESET_LINK_SENT
                    ? redirect()->route('password.sent')->with('reset_email', $this->maskEmail($user->email))
                    : back()->withInput($request->only('username'))
                        ->withErrors(['username' => __($status)]);
    }

    /**
     * Mask the email address for display on the confirmation page.
     */
    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = explode('@', $email, 2);

        return substr($localPart, 0, 2).'*****@'.$domain;
    }
}
