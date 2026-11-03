<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Staff
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::user()?->isStaff()) {
            // The panel has no login page of its own: staff sign in through the normal customer login.
            if ($request->expectsJson()) return response()->json(['ok' => false, 'error' => 'Not signed in'], 401);
            abort(404);
        }
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
