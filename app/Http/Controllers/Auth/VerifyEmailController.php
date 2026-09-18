<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::findOrFail($request->route('id'));
        $hash = (string) $request->route('hash');

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->hasVerifiedEmail()) {
            return $this->redirectAfterVerification($user);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user->forceFill(['is_active' => true])->save();

        AuditLog::activity('account_activated', $user->id, sprintf('Activated account for %s.', $user->name));

        return $this->redirectAfterVerification($user);
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
