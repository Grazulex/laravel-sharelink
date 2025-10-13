<?php

declare(strict_types=1);

use Grazulex\ShareLink\Facades\ShareLink;

it('returns JSON model preview fallback in debug for model targets', function (): void {
    config()->set('app.debug', true);

    $link = ShareLink::create([
        'type' => 'model',
        'class' => 'App\\Models\\Post',
        'id' => 123,
    ])->generate();

    $this->get('/share/'.$link->token)
        ->assertStatus(200)
        ->assertJsonPath('code', 'sharelink.model_preview')
        ->assertJsonPath('model.class', 'App\\Models\\Post')
        ->assertJsonPath('model.id', 123);
});
