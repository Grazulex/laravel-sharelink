<?php

declare(strict_types=1);

use Grazulex\ShareLink\Events\ShareLinkRevoked;
use Grazulex\ShareLink\Services\ShareLinkManager;
use Illuminate\Support\Facades\Event;

it('revokes a link and dispatches event', function (): void {
    Event::fake();

    $model = (new ShareLinkManager())->create('/tmp/revoke.txt')->generate();

    $revoker = new Grazulex\ShareLink\Services\ShareLinkRevoker();
    $revoker->revoke($model);

    expect($model->fresh()->revoked_at)->not->toBeNull();
    Event::assertDispatched(ShareLinkRevoked::class);
});
