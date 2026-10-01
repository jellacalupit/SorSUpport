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
        $email = (string) ($request->user()?->email ?? 'your registered email');
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $maskedEmail = $domain !== ''
            ? substr($localPart, 0, 2) . '*****@' . $domain
            : $email;

        return view('auth.verify-email', compact('maskedEmail'));
    }


}
