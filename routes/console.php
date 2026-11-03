<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kiosk:automations', function () {
    $n = \App\Support\Marketing::runDue(100);
    $this->info("Automations run — $n email(s) sent.");
})->purpose('Send due abandoned-cart / lifecycle emails');

\Illuminate\Support\Facades\Schedule::command('kiosk:automations')->everyFiveMinutes()->withoutOverlapping();
