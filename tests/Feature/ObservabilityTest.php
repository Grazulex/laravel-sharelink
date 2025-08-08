<?php

declare(strict_types=1);

use Grazulex\ShareLink\Events\ShareLinkAccessed;
use Grazulex\ShareLink\Events\ShareLinkCreated;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

it('logs created and accessed events when observability enabled', function (): void {
    config()->set('sharelink.observability.enabled', true);
    config()->set('sharelink.observability.log', true);

    Log::spy();

    $m = ShareLink::create(['resource' => '/tmp/foo.txt', 'token' => 'obs-'.bin2hex(random_bytes(3))]);
    event(new ShareLinkCreated($m));
    event(new ShareLinkAccessed($m));

    Log::shouldHaveReceived('info')->with('ShareLink created', \Mockery::on(fn ($a) => is_array($a)))->once();
    Log::shouldHaveReceived('info')->with('ShareLink accessed', \Mockery::on(fn ($a) => is_array($a)))->once();
});
