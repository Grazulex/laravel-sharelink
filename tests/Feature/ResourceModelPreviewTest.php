<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;

it('returns JSON model preview fallback in debug for model targets', function (): void {
    config()->set('app.debug', true);

    $link = ShareLink::create([
        'resource' => [
            'type' => 'model',
            'class' => 'App\\Models\\Post',
            'id' => 123,
        ],
        'token' => 'md-'.bin2hex(random_bytes(3)),
    ]);

    $this->get('/share/'.$link->token)
        ->assertStatus(200)
        ->assertJsonPath('code', 'sharelink.model_preview')
        ->assertJsonPath('model.class', 'App\\Models\\Post')
        ->assertJsonPath('model.id', 123);
});
