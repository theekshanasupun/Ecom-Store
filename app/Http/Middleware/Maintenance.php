<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Maintenance mode (Admin → Settings) and the short "updating" lock used by the system updater. */
class Maintenance
{
    public function handle(Request $request, Closure $next)
    {
        if (! installed() || $request->is('admin', 'admin/*')) {
            return $next($request);
        }
        $flag = storage_path('framework/updating.flag');
        if (is_file($flag) && time() - (int) @file_get_contents($flag) < 300) {
            return response('<h1 style="font-family:sans-serif">Updating…</h1><p style="font-family:sans-serif">Back in a minute.</p>', 503, ['Retry-After' => 60]);
        }
        if (setting('maintenance') === '1' && ! Auth::user()?->isStaff()) {
            return response(view('maintenance')->render(), 503, ['Retry-After' => 600]);
        }

        return $next($request);
    }
}
