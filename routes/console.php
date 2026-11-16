<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the "INOTEK Scheduler" task on the server running `php artisan schedule:run` every minute.
Schedule::command('inotek:prune')->dailyAt('03:00')->withoutOverlapping();
