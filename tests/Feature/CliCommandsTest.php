<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;

it('creates a link via CLI', function (): void {
    $this->artisan('sharelink:create', [
        'resource' => '/tmp/foo.txt',
        '--expires' => 1,
        '--max-clicks' => 2,
        '--password' => 's3cret',
        '--burn' => true,
    ])->assertExitCode(0)->run();

    expect(ShareLink::query()->count())->toBe(1);
});

it('lists links via CLI', function (): void {
    ShareLink::create(['resource' => '/tmp/foo.txt', 'token' => 'a']);
    $this->artisan('sharelink:list')->assertExitCode(0)->run();
});

it('revokes a link via CLI', function (): void {
    $m = ShareLink::create(['resource' => '/tmp/foo.txt', 'token' => 'b']);
    $this->artisan('sharelink:revoke', ['token' => $m->token])->assertExitCode(0)->run();
    $m->refresh();
    expect($m->revoked_at)->not->toBeNull();
});
