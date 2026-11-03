<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Admin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Only administrators can open this page.');

        return $next($request);
    }
}
