<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_STUDENT,
        ]);

        event(new Registered($user));

        AuditLog::activity('account_registered', $user->id, sprintf('Registered account for %s.', $user->name));

        Auth::login($user);

        return match ($user->role) {
            User::ROLE_SDS_ADMIN => redirect()->route('admin.dashboard'),
            User::ROLE_STUDENT => redirect()->route('student.dashboard'),
            User::ROLE_RECIPIENT => redirect()->route('recipient.dashboard'),
            default => redirect()->route('login'),
        };
    }
}
