<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return match ($request->user()->role) {
                User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard'),
                User::ROLE_STUDENT => redirect()->route('student.dashboard'),
                User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard'),
                default => redirect()->route('dashboard'),
            };
        }

        return view('auth.verify-email');
    }
}
