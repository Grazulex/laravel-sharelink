<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Hash;

it('throttles repeated wrong passwords', function (): void {
    config()->set('sharelink.limits.password.enabled', true);
    config()->set('sharelink.limits.password.max', 2);
    config()->set('sharelink.limits.password.decay', 60);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'pwdthr-'.bin2hex(random_bytes(6)),
        'password' => Hash::make('secret123'),
    ]);

    // Two wrong attempts
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(401);
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(401);
    // Third attempt is throttled
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(429)->assertJsonPath('code', 'password.throttled');
});

it('resets throttle after a successful password', function (): void {
    config()->set('sharelink.limits.password.enabled', true);
    config()->set('sharelink.limits.password.max', 2);
    config()->set('sharelink.limits.password.decay', 60);

    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'pwdthr-'.bin2hex(random_bytes(6)),
        'password' => Hash::make('secret123'),
    ]);

    // One wrong attempt
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(401);
    // Now correct -> should pass and clear the limiter
    $this->get('/share/'.$model->token.'?password=secret123')->assertStatus(200);

    // After success, two wrong attempts should be allowed before throttling again
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(401);
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(401);
    $this->get('/share/'.$model->token.'?password=wrong')->assertStatus(429)->assertJsonPath('code', 'password.throttled');
});
