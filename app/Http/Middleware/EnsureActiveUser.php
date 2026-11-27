<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been deactivated by an administrator.',
                ]);
            }

            // Enforce 2FA verification when enabled on user or mandatory for Super Admin in production
            $require2fa = $user->two_factor_enabled || (
                $user->isSuperAdmin()
                && app()->environment('production')
                && (bool) config('ats.require_super_admin_2fa', true)
                && ! empty($user->two_factor_secret)
            );

            if ($require2fa && ! $request->session()->get('two_factor_verified', false)) {
                if (! $request->routeIs('two-factor.*') && ! $request->routeIs('logout')) {
                    return redirect()->route('two-factor.challenge');
                }
            }
        }

        $response = $next($request);

        // Apply Enterprise Security Headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
