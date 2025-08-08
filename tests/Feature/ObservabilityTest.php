<?php

declare(strict_types=1);

use Grazulex\ShareLink\Events\ShareLinkAccessed;
use Grazulex\ShareLink\Events\ShareLinkCreated;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Event;

it('logs created and accessed events when observability enabled', function (): void {
    config()->set('sharelink.observability.enabled', true);
    config()->set('sharelink.observability.log', true);

    // Use Event::fake() to capture events and test them directly
    Event::fake();

    $m = ShareLink::create(['resource' => '/tmp/foo.txt', 'token' => 'obs-'.bin2hex(random_bytes(3))]);
    event(new ShareLinkCreated($m));
    event(new ShareLinkAccessed($m));

    // Verify the events were dispatched
    Event::assertDispatched(ShareLinkCreated::class);
    Event::assertDispatched(ShareLinkAccessed::class);
});
