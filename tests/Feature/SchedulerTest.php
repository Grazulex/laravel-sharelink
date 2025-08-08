<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('registers a scheduled prune command when enabled', function (): void {
    config()->set('sharelink.schedule.prune.enabled', true);
    config()->set('sharelink.schedule.prune.expression', '* * * * *');

    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    /** @var Event|null $evt */
    $evt = $events->first(function (Event $e): bool {
        return str_contains((string) $e->command, 'sharelink:prune');
    });

    expect($evt)->not->toBeNull();
});

it('does not register scheduled prune when disabled', function (): void {
    config()->set('sharelink.schedule.prune.enabled', false);

    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());
    $hasPrune = $events->contains(function (Event $e): bool {
        return str_contains((string) $e->command, 'sharelink:prune');
    });

    expect($hasPrune)->toBeFalse();
});
