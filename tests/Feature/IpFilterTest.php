<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Hash;

it('denies when global deny list matches', function (): void {
    config()->set('sharelink.limits.ip.deny', ['127.0.0.1']);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'ipflt-'.bin2hex(random_bytes(6)),
        'password' => null,
    ]);

    $this->get('/share/'.$model->token)->assertStatus(403)->assertJsonPath('code', 'sharelink.ip_denied');
});

it('allows only IPs in global allow list', function (): void {
    config()->set('sharelink.limits.ip.allow', ['127.0.0.1']);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'ipflt-'.bin2hex(random_bytes(6)),
        'password' => null,
    ]);

    // Localhost is allowed
    $this->get('/share/'.$model->token)->assertStatus(200);
});

it('per-link metadata allow/deny overrides apply', function (): void {
    // Global allow empty, use per-link
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'ipflt-'.bin2hex(random_bytes(6)),
        'password' => Hash::make('s'),
        'metadata' => [
            'ip_allow' => ['127.0.0.1'],
            'ip_deny' => [],
        ],
    ]);

    // Correct password required to pass controller
    $this->get('/share/'.$model->token.'?password=s')->assertStatus(200);

    // Change to deny current IP and expect 403
    $model->metadata = ['ip_deny' => ['127.0.0.1']];
    $model->save();

    $this->get('/share/'.$model->token.'?password=s')->assertStatus(403)->assertJsonPath('code', 'sharelink.ip_denied');
});
