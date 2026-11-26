<?php

declare(strict_types=1);

use Grazulex\ShareLink\Facades\ShareLink;
use Illuminate\Support\Facades\Route;

it('redirects to a named route when resource type is route', function (): void {
    Route::get('/hello/{name}', fn (string $name) => 'Hello '.$name)->name('hello');

    $link = ShareLink::create([
        'type' => 'route',
        'name' => 'hello',
        'params' => ['name' => 'World'],
    ])->generate();

    $this->get('/share/'.$link->token)
        ->assertRedirect(route('hello', ['name' => 'World']));
});

it('redirects to a named route when using the "route" and "parameters" aliases', function (): void {
    Route::get('/users/{user}', fn (string $user) => 'User '.$user)->name('user.profile');

    $link = ShareLink::create([
        'type' => 'route',
        'route' => 'user.profile',
        'parameters' => ['user' => 123],
    ])->expiresIn(2)->generate();

    $this->get('/share/'.$link->token)
        ->assertRedirect(route('user.profile', ['user' => 123]));
});

it('redirects using aliased keys stored directly on the model', function (): void {
    Route::get('/users/{user}', fn (string $user) => 'User '.$user)->name('user.profile');

    $link = Grazulex\ShareLink\Models\ShareLink::create([
        'resource' => ['type' => 'route', 'route' => 'user.profile', 'parameters' => ['user' => 7]],
        'token' => 'route-alias-raw',
    ]);

    $this->get('/share/'.$link->token)
        ->assertRedirect(route('user.profile', ['user' => 7]));
});
