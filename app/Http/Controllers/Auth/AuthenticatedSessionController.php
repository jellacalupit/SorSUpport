<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var User|null $user */
        $user = Auth::user();

        AuditLog::activity('user_logged_in', details: sprintf('User %s logged in.', $user->name));

        // Email verification is only for inactive accounts; one the admin activated skips it.
        if ($user->is_active && ! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // An account still using its ID as the password must set a new one first.
        if (! $user->must_change_password && filled($user->username) && $request->input('password') === $user->username) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        /*
        |--------------------------------------------------------------------------
        | Force Password Change
        |--------------------------------------------------------------------------
        */

        if ($user->must_change_password) {
            if ($user->isSdsAdmin()) {
                return redirect()->route('profile.edit');
            }

            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                return redirect()->route('verification.notice');
            }

            return redirect()->route('password.force');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return match ($user->role) {
            User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard'),
            User::ROLE_STUDENT => redirect()->route('student.dashboard'),
            User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard'),
            default => redirect('/'),
        };
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}