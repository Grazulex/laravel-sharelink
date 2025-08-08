<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;

it('can revoke a link via HTTP endpoint', function (): void {
    config()->set('sharelink.management.enabled', true);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'revoke-'.bin2hex(random_bytes(6)),
    ]);

    $this->post('/share/'.$model->token.'/revoke')
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'resource', 'metadata', 'clicks']]);

    expect($model->fresh()->revoked_at)->not->toBeNull();
});

it('can extend a link via HTTP endpoint', function (): void {
    config()->set('sharelink.management.enabled', true);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'extend-'.bin2hex(random_bytes(6)),
        'expires_at' => now()->addHour(),
    ]);

    $this->post('/share/'.$model->token.'/extend', ['hours' => 2])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'resource', 'metadata', 'clicks']]);

    expect($model->fresh()->expires_at->greaterThan(now()->addHours(2)->subMinute()))->toBeTrue();
});
