<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Production runs `* * * * * php artisan schedule:run` (see deploy/). Pinned by
 * tests/Feature/ScheduleTest.php.
 */
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-failed')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => DB::table('notifications')
    ->whereNotNull('read_at')
    ->where('read_at', '<', now()->subDays(90))
    ->delete())
    ->name('prune read notifications')
    ->daily();
