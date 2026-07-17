<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectAfterVerification($request->user());
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $this->redirectAfterVerification($request->user());
    }

    private function redirectAfterVerification(User $user): RedirectResponse
    {
        if ($user->must_change_password) {
            return redirect()->route('password.force');
        }

        return match ($user->role) {
            User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard')->with('verified', true),
            User::ROLE_STUDENT => redirect()->route('student.dashboard')->with('verified', true),
            User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard')->with('verified', true),
            default => redirect('/')->with('verified', true),
        };
    }
}
