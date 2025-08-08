<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Services\ShareLinkManager;

it('generates a URL and applies password hashing when provided', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create('/tmp/example.pdf')
        ->expiresIn(2)
        ->maxClicks(3)
        ->withPassword('secret')
        ->generate();

    expect($model)->toBeInstanceOf(ShareLink::class)
        ->and($model->token)->toBeString()
        ->and($model->getAttribute('url'))
        ->toContain('/share/')
        ->and($model->password)->not->toBe('secret');
});
