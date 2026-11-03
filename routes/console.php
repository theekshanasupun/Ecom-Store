<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kiosk:automations', function () {
    $n = \App\Support\Marketing::runDue(100);
    $g = \App\Services\GiftCards::sendDue(50);
    $this->info("Automations run — $n email(s) sent, $g scheduled gift card(s) delivered.");
})->purpose('Send due abandoned-cart / lifecycle emails');

Artisan::command('kiosk:loyalty {--force}', function () {
    $r = \App\Services\LoyaltyEngagement::tick((bool) $this->option('force'));
    $this->info('Loyalty maintenance: '.json_encode($r));
})->purpose('Release held points, expire points, send reminders, birthdays, win-back, competitions');

\Illuminate\Support\Facades\Schedule::command('kiosk:loyalty')->hourly()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('kiosk:automations')->everyFiveMinutes()->withoutOverlapping();
