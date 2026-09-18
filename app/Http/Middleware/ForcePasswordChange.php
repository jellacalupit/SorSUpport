<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        // If not logged in, continue normally.
        if (! $user) {
            return $next($request);
        }

        // User must change password first.
        if (
            $user->must_change_password &&
            ! $request->routeIs('password.force') &&
            ! $request->routeIs('password.force.update') &&
            ! $request->routeIs('profile.edit') &&
            ! $request->routeIs('profile.update') &&
            ! $request->routeIs('profile.photo') &&
            ! $request->routeIs('password.update') &&
            ! $request->routeIs('logout')
        ) {
            if ($user->isSdsAdmin()) {
                return redirect()->route('profile.edit');
            }

            return redirect()->route('password.force');
        }

        return $next($request);
    }
}