<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();
        $sessionGeneration = $request->hasSession() ? $request->session()->get('auth_generation') : null;
        if ($request->hasSession() && $sessionGeneration === null) {
            $request->session()->put('auth_generation', $user->auth_generation);
        }
        if (! $user->isActive() || ($sessionGeneration !== null && (int) $sessionGeneration !== (int) $user->auth_generation)) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403, 'This account is disabled.');
        }

        return $next($request);
    }
}
