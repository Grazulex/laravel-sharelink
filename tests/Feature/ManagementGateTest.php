<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config()->set('sharelink.management.enabled', true);
    config()->set('sharelink.route.prefix', 'share');
    config()->set('sharelink.management.middleware', []);
    config()->set('sharelink.management.gate', 'manage-sharelinks');
});

it('denies management actions when gate denies', function (): void {
    $model = ShareLink::create(['resource' => '/tmp/foo.txt', 'token' => 'gate-'.bin2hex(random_bytes(3))]);

    Gate::define('manage-sharelinks', function (?object $user, $link): bool {
        return false;
    });

    $this->post('/share/'.$model->token.'/revoke')->assertStatus(403)->assertJsonPath('code', 'sharelink.forbidden');
    $this->post('/share/'.$model->token.'/extend', ['hours' => 1])->assertStatus(403)->assertJsonPath('code', 'sharelink.forbidden');
});

it('allows management actions when gate allows', function (): void {
    $model = ShareLink::create(['resource' => '/tmp/foo.txt', 'token' => 'gate-'.bin2hex(random_bytes(3))]);

    Gate::define('manage-sharelinks', function (?object $user, $link): bool {
        return true;
    });

    $this->post('/share/'.$model->token.'/revoke')->assertStatus(200);
    $this->post('/share/'.$model->token.'/extend', ['hours' => 2])->assertStatus(200);
});
