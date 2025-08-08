<?php

declare(strict_types=1);

use Grazulex\ShareLink\Services\ShareLinkManager;

it('revokes after first successful access when burn-after-reading is set', function (): void {
    config()->set('sharelink.burn.enabled', true);
    config()->set('sharelink.burn.auto_max_clicks', false);

    $model = app(ShareLinkManager::class)
        ->create('/tmp/foo.txt')
        ->burnAfterReading()
        ->generate();

    // First access succeeds
    $this->get('/share/'.$model->token)->assertStatus(200);

    // Subsequent access should be invalid (410) due to revocation
    $this->get('/share/'.$model->token)->assertStatus(410)->assertJsonPath('code', 'sharelink.invalid');
});

it('auto treats max_clicks=1 as burn if configured', function (): void {
    config()->set('sharelink.burn.enabled', true);
    config()->set('sharelink.burn.auto_max_clicks', true);

    $model = app(ShareLinkManager::class)
        ->create('/tmp/foo.txt')
        ->maxClicks(1)
        ->generate();

    $this->get('/share/'.$model->token)->assertStatus(200);
    $this->get('/share/'.$model->token)->assertStatus(410);
});
