<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientIsolation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isClient()) {
            if (! $user->client_id) {
                abort(403, 'Client account is not associated with an active client organisation.');
            }

            // Verify any bound model on the route belongs to this client's organisation
            foreach ($request->route()?->parameters() ?? [] as $param) {
                if (is_object($param) && isset($param->client_id)) {
                    if ((int) $param->client_id !== (int) $user->client_id) {
                        abort(403, 'Cross-client data isolation violation blocked.');
                    }
                }
            }
        }

        return $next($request);
    }
}
