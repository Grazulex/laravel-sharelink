<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Notifications\ShareLinkAccessed as AccessedNotification;
use Grazulex\ShareLink\Notifications\ShareLinkDeadlineMissed;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    config()->set('sharelink.notifications.enabled', true);
    config()->set('sharelink.notifications.to', 'owner@example.test');
    Notification::fake();
});

function notifyTestFile(): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'sln');
    file_put_contents($tmp, 'data');

    return $tmp;
}

it('notifies the owner on first access only', function (): void {
    $link = ShareLink::create(['resource' => ['type' => 'route', 'name' => 'x'], 'token' => 'n-first']);

    $this->get('/share/'.$link->token);
    $this->get('/share/'.$link->token);

    Notification::assertSentOnDemandTimes(AccessedNotification::class, 1);
    Notification::assertSentOnDemand(AccessedNotification::class, function ($n, $channels, AnonymousNotifiable $notifiable): bool {
        return $n->reason === AccessedNotification::REASON_FIRST_ACCESS
            && $notifiable->routes['mail'] === 'owner@example.test';
    });
});

it('sends nothing when notifications are disabled', function (): void {
    config()->set('sharelink.notifications.enabled', false);
    $link = ShareLink::create(['resource' => ['type' => 'route', 'name' => 'x'], 'token' => 'n-off']);

    $this->get('/share/'.$link->token);

    Notification::assertNothingSent();
});

it('uses metadata notify as recipient and honours opt-out', function (): void {
    $a = ShareLink::create(['resource' => 'x', 'token' => 'n-meta', 'metadata' => ['notify' => 'custom@example.test']]);
    $b = ShareLink::create(['resource' => 'x', 'token' => 'n-optout', 'metadata' => ['notify' => false]]);

    $this->get('/share/'.$a->token);
    $this->get('/share/'.$b->token);

    Notification::assertSentOnDemandTimes(AccessedNotification::class, 1);
    Notification::assertSentOnDemand(AccessedNotification::class, fn ($n, $c, $notifiable): bool => $notifiable->routes['mail'] === 'custom@example.test');
});

it('notifies when the click limit is reached', function (): void {
    $link = ShareLink::create(['resource' => 'x', 'token' => 'n-click', 'max_clicks' => 2]);

    $this->get('/share/'.$link->token);
    $this->get('/share/'.$link->token);

    Notification::assertSentOnDemand(AccessedNotification::class, fn ($n): bool => $n->reason === AccessedNotification::REASON_CLICK_LIMIT);
});

it('notifies when the download limit is reached', function (): void {
    $tmp = notifyTestFile();
    $link = ShareLink::create(['resource' => $tmp, 'token' => 'n-dl', 'metadata' => ['max_downloads' => 1, 'notify' => 'dl@example.test']]);

    $this->get('/share/'.$link->token)->assertOk();

    Notification::assertSentOnDemand(AccessedNotification::class, fn ($n): bool => $n->reason === AccessedNotification::REASON_DOWNLOAD_LIMIT);
    @unlink($tmp);
});

it('notifies once about links that expired without any access', function (): void {
    $missed = ShareLink::create(['resource' => 'x', 'token' => 'n-missed', 'expires_at' => now()->subHour()]);
    $opened = ShareLink::create(['resource' => 'x', 'token' => 'n-opened', 'expires_at' => now()->subHour()]);
    $opened->forceFill(['first_access_at' => now()->subDay()])->saveQuietly();
    ShareLink::create(['resource' => 'x', 'token' => 'n-future', 'expires_at' => now()->addHour()]);

    $this->artisan('sharelink:notify-missed')->assertSuccessful();
    $this->artisan('sharelink:notify-missed')->assertSuccessful();

    Notification::assertSentOnDemandTimes(ShareLinkDeadlineMissed::class, 1);
    expect($missed->fresh()->metadata)->toHaveKey('deadline_missed_notified_at');
});

it('does not notify about missed deadlines when disabled', function (): void {
    config()->set('sharelink.notifications.events.deadline_missed', false);
    ShareLink::create(['resource' => 'x', 'token' => 'n-missed-off', 'expires_at' => now()->subHour()]);

    $this->artisan('sharelink:notify-missed')->assertSuccessful();

    Notification::assertNothingSent();
});
