<?php

declare(strict_types=1);

use Grazulex\ShareLink\Http\Controllers\ShareLinkController;
use Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid;
use Grazulex\ShareLink\Models\ShareLink;
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

    $this->get('/test-share/'.$model->token)
        ->assertStatus(410);
});

it('returns 429 when usage limit is reached', function (): void {
    $model = ShareLink::create([
        'resource' => '/tmp/foo.txt',
        'token' => 'limittoken',
        'max_clicks' => 1,
        'click_count' => 1,
    ]);

    $this->get('/test-share/'.$model->token)
        ->assertStatus(429);
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

    expect($model->fresh()->click_count)->toBe(1);
});
