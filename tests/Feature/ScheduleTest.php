<?php

use Illuminate\Console\Scheduling\Schedule;

/*
 * Production needs one cron line (`* * * * * php artisan schedule:run`); this
 * pins what that line runs so a missing entry is a red test, not a blank
 * Horizon metrics tab or a notifications table that only grows.
 */
test('the scheduler prunes the queue, tokens and read notifications and snapshots horizon', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event): string => $event->command ?? $event->description ?? '')
        ->implode("\n");

    expect($commands)
        ->toContain('horizon:snapshot')
        ->toContain('queue:prune-failed')
        ->toContain('sanctum:prune-expired')
        ->toContain('prune read notifications');
});
