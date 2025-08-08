<?php

declare(strict_types=1);

use Grazulex\ShareLink\Services\ShareLinkManager;

it('can create a share link with expiration', function () {
    $model = (new ShareLinkManager())->create('/tmp/example.txt')
        ->expiresIn(1)
        ->maxClicks(2)
        ->generate();

    expect($model->token)->toBeString();
    expect($model->expires_at)->not()->toBeNull();
    expect($model->max_clicks)->toBe(2);
});
