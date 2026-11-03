<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \App\Models\User::created(fn ($u) => \App\Services\Loyalty::onUserCreated($u));
        if (installed()) {
            try {
                $tz = (string) setting('timezone', config('app.timezone'));
                if (in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
                    config(['app.timezone' => $tz]);
                    date_default_timezone_set($tz);
                }
            } catch (\Throwable) {
            }
        }
    }
}
