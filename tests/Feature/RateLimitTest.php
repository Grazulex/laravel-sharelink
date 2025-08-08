<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;

it('applies per-token rate limiting', function (): void {
    config()->set('sharelink.limits.rate.enabled', true);
    config()->set('sharelink.limits.rate.max', 2);
    config()->set('sharelink.limits.rate.decay', 60);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'rate-'.bin2hex(random_bytes(6)),
    ]);

    // First two requests pass
    $this->get('/share/'.$model->token)->assertStatus(200);
    $this->get('/share/'.$model->token)->assertStatus(200);
    // Third is rate limited
    $this->get('/share/'.$model->token)->assertStatus(429)->assertJsonPath('code', 'sharelink.rate_limited');
});
