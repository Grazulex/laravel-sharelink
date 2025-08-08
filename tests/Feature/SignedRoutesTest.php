<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Services\ShareLinkManager;

beforeEach(function (): void {
    config()->set('sharelink.signed.enabled', true);
    config()->set('sharelink.signed.required', false);
});

it('allows access with a valid temporary signed URL', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'signed-'.bin2hex(random_bytes(6)),
    ]);

    $url = app(ShareLinkManager::class)->signedUrl($model, 5);

    $this->get($url)->assertOk();
});

it('rejects invalid signature when signature present', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'signedbad-'.bin2hex(random_bytes(6)),
    ]);

    $url = route('sharelink.show', ['token' => $model->token]).'?signature=invalid';
    $this->get($url)->assertStatus(403)->assertJsonPath('code', 'sharelink.signature_invalid');
});

it('requires signature when configured', function (): void {
    config()->set('sharelink.signed.required', true);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'signedreq-'.bin2hex(random_bytes(6)),
    ]);

    $this->get(route('sharelink.show', ['token' => $model->token]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'sharelink.signature_required');
});
