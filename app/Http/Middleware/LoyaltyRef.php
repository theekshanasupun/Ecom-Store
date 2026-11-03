<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Remember a referral code (?ref=CODE) for 60 days so it can be attached when the visitor signs up. */
class LoyaltyRef
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $ref = $request->query('ref');
        if (is_string($ref) && preg_match('/^[A-Za-z0-9]{3,24}$/', $ref) && $request->isMethod('GET') && ! str_starts_with($request->path(), 'admin')) {
            $response->headers->setCookie(cookie('kref', strtoupper($ref), 60 * 24 * 60, '/', null, null, true, false, 'lax'));
        }

        return $response;
    }
}
