<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Services\ShareLinkManager;

it('extends a link expiration', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'extend-'.bin2hex(random_bytes(6)),
        'expires_at' => now()->addHour(),
    ]);

    $manager = app(ShareLinkManager::class);
    $manager->extend($model, 2);

    expect($model->fresh()->expires_at->greaterThan(now()->addHours(2)->subMinute()))->toBeTrue();
});
