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
