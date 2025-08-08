<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Illuminate\Support\Facades\Route;

it('redirects to a named route when resource type is route', function (): void {
    Route::get('/hello/{name}', fn (string $name) => 'Hello '.$name)->name('hello');

    $link = ShareLink::create([
        'resource' => [
            'type' => 'route',
            'name' => 'hello',
            'params' => ['name' => 'World'],
        ],
        'token' => 'rt-'.bin2hex(random_bytes(3)),
    ]);

    $this->get('/share/'.$link->token)
        ->assertRedirect(route('hello', ['name' => 'World']));
});
