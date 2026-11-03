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
            return $request->expectsJson() ? response()->json(['ok' => false, 'error' => 'Not signed in'], 401) : redirect('/admin/login');
        }
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
