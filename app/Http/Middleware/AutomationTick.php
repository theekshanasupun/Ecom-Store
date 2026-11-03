<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * "Pseudo-cron" for hosts without cron: after the response has been sent, at most every 5 minutes
 * run due email automations. A real cron (`php artisan schedule:run`) works too — both are safe together.
 */
class AutomationTick
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (! installed() || $request->isMethod('POST') || str_starts_with($request->path(), 'admin')) return;
        try {
            if (setting('automations_autorun') !== '1') return;
            if (! Cache::add('kiosk.automation.tick', 1, 300)) return;
            \App\Support\Marketing::runDue(8);
        } catch (\Throwable $e) {
            \Log::debug('automation tick: '.$e->getMessage());
        }
    }
}
