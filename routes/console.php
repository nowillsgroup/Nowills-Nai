<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nai:monitor')->everyFiveMinutes()->withoutOverlapping()->when(fn () => (bool) config('nai.monitoring.enabled'));

Schedule::command('nai:monitor-prune')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('nai:import-prune')->hourly()->withoutOverlapping();

// A dedicated database queue; the scheduler must be invoked by cron.
Schedule::command('queue:work database --queue=workforce --stop-when-empty --max-time=50 --timeout=75 --tries=1')->everyMinute()->withoutOverlapping(5);
