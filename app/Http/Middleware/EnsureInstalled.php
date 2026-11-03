<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Sends visitors to the web installer until KIOSK is installed (and hides the installer afterwards). */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        $isInstaller = $request->is('install', 'install/*');
        if (! installed() && ! $isInstaller) {
            return redirect('/install');
        }
        if (installed() && $isInstaller && ! $request->is('install/done')) {
            return redirect('/');
        }

        return $next($request);
    }
}
