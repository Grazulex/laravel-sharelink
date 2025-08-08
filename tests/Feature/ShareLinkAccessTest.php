<?php

declare(strict_types=1);

use Grazulex\ShareLink\Events\ShareLinkExpired;
use Grazulex\ShareLink\Http\Controllers\ShareLinkController;
use Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid;
use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // Define a test route protected by the package middleware to avoid coupling to package route file.
    Route::middleware([EnsureShareLinkIsValid::class])
        ->get('/test-share/{token}', [ShareLinkController::class, 'show']);
});

it('returns 410 for expired links', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'expiredtoken',
        'expires_at' => now()->subMinute(),
        'click_count' => 0,
    ]);

    Event::fake([ShareLinkExpired::class]);

    $this->get('/test-share/'.$model->token)
        ->assertStatus(410)
        ->assertJsonPath('code', 'sharelink.invalid');

    Event::assertDispatched(ShareLinkExpired::class, function ($event) use ($model): bool {
        return $event->shareLink->is($model);
    });
});

it('returns 429 when usage limit is reached', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'limittoken',
        'max_clicks' => 1,
        'click_count' => 1,
    ]);

    $this->get('/test-share/'.$model->token)
        ->assertStatus(429)
        ->assertJsonPath('code', 'sharelink.limit_reached');
});

it('requires password when set and accepts correct one', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'pwdtoken',
        'password' => Hash::make('secret123'),
        'click_count' => 0,
    ]);

    // Missing password
    $this->get('/test-share/'.$model->token)
        ->assertStatus(401);

    // Wrong password
    $this->get('/test-share/'.$model->token.'?password=wrong')
        ->assertStatus(401);

    // Correct password
    $this->get('/test-share/'.$model->token.'?password=secret123')
        ->assertOk()
        ->assertJsonStructure(['token', 'resource', 'metadata', 'clicks']);
});

it('increments click count on access', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'clicktoken',
        'click_count' => 0,
    ]);

    $this->get('/test-share/'.$model->token)->assertOk();

    $fresh = $model->fresh();
    expect($fresh->click_count)->toBe(1)
        ->and($fresh->first_access_at)->not->toBeNull()
        ->and($fresh->last_access_at)->not->toBeNull();
});

it('prunes expired links with artisan command', function (): void {
    Event::fake([ShareLinkExpired::class]);

    $expired = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'expire1',
        'expires_at' => now()->subDay(),
    ]);
    ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'revoked1',
        'revoked_at' => now()->subDays(2),
    ]);

    $this->artisan('sharelink:prune')->assertExitCode(0);

    expect(ShareLink::query()->count())->toBe(0);

    Event::assertDispatched(ShareLinkExpired::class, function ($event) use ($expired): bool {
        return $event->shareLink->is($expired);
    });
});
