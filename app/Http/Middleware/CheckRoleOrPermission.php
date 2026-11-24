<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleOrPermission
{
    public function handle(Request $request, Closure $next, string ...$requirements): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Prevent client portal users from accessing internal CRM routes unless explicitly allowed
        if ($user->isClient() && ! in_array('client', $requirements, true)) {
            abort(403, 'Access denied: Client users are restricted to the Client Portal.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($requirements as $req) {
            if ($user->hasRole($req) || $user->hasPermission($req)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorised action: Your role does not have permission to access this module.');
    }
}
