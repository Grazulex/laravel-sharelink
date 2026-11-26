<?php

declare(strict_types=1);

use Grazulex\ShareLink\Models\ShareLink;
use Grazulex\ShareLink\Services\ShareLinkManager;

it('generates a URL and applies password hashing when provided', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create('/tmp/example.pdf')
        ->expiresIn(2)
        ->maxClicks(3)
        ->withPassword('secret')
        ->generate();

    expect($model)->toBeInstanceOf(ShareLink::class)
        ->and($model->token)->toBeString()
        ->and($model->url)
        ->toContain('/share/')
        ->and($model->password)->not->toBe('secret');
});

it('accepts array resource for route type', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create([
        'type' => 'route',
        'name' => 'user.profile',
        'params' => ['id' => 123],
    ])->generate();

    expect($model)->toBeInstanceOf(ShareLink::class)
        ->and($model->resource)->toBeArray()
        ->and($model->resource['type'])->toBe('route')
        ->and($model->resource['name'])->toBe('user.profile')
        ->and($model->resource['params'])->toBe(['id' => 123]);
});

it('accepts "route" and "parameters" as aliases of "name" and "params"', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create([
        'type' => 'route',
        'route' => 'user.profile',
        'parameters' => ['user' => 123],
    ])->generate();

    expect($model->resource)->toBe([
        'type' => 'route',
        'name' => 'user.profile',
        'params' => ['user' => 123],
    ]);
});

it('accepts mixed canonical and aliased route keys', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create([
        'type' => 'route',
        'route' => 'user.profile',
        'params' => ['user' => 1],
    ])->generate();

    expect($model->resource['name'])->toBe('user.profile')
        ->and($model->resource['params'])->toBe(['user' => 1])
        ->and($model->resource)->not->toHaveKeys(['route', 'parameters']);
});

it('prefers canonical route keys over their aliases when both are given', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create([
        'type' => 'route',
        'name' => 'canonical.route',
        'route' => 'alias.route',
        'params' => ['a' => 1],
        'parameters' => ['b' => 2],
    ])->generate();

    expect($model->resource)->toBe([
        'type' => 'route',
        'name' => 'canonical.route',
        'params' => ['a' => 1],
    ]);
});

it('allows a route resource without parameters', function (): void {
    $model = (new ShareLinkManager())->create([
        'type' => 'route',
        'route' => 'dashboard',
    ])->generate();

    expect($model->resource)->toBe(['type' => 'route', 'name' => 'dashboard']);
});

it('throws exception when the "route" alias is empty', function (): void {
    (new ShareLinkManager())->create([
        'type' => 'route',
        'route' => '',
        'parameters' => ['user' => 123],
    ]);
})->throws(InvalidArgumentException::class, 'Route resource must have a non-empty "name" key (or its alias "route").');

it('throws exception when the "parameters" alias is not an array', function (): void {
    (new ShareLinkManager())->create([
        'type' => 'route',
        'route' => 'user.profile',
        'parameters' => 'not-an-array',
    ]);
})->throws(InvalidArgumentException::class, 'Route resource "params" (or its alias "parameters") must be an array.');

it('accepts array resource for model type', function (): void {
    $manager = new ShareLinkManager();

    $model = $manager->create([
        'type' => 'model',
        'class' => 'App\\Models\\Post',
        'id' => 456,
    ])->generate();

    expect($model)->toBeInstanceOf(ShareLink::class)
        ->and($model->resource)->toBeArray()
        ->and($model->resource['type'])->toBe('model')
        ->and($model->resource['class'])->toBe('App\\Models\\Post')
        ->and($model->resource['id'])->toBe(456);
});

it('throws exception when array resource is missing type', function (): void {
    $manager = new ShareLinkManager();

    $manager->create([
        'name' => 'route.name',
    ]);
})->throws(InvalidArgumentException::class, 'Array resource must have a "type" key.');

it('throws exception when route resource is missing name', function (): void {
    $manager = new ShareLinkManager();

    $manager->create([
        'type' => 'route',
    ]);
})->throws(InvalidArgumentException::class, 'Route resource must have a non-empty "name" key (or its alias "route").');

it('throws exception when route resource has invalid params', function (): void {
    $manager = new ShareLinkManager();

    $manager->create([
        'type' => 'route',
        'name' => 'test',
        'params' => 'not-an-array',
    ]);
})->throws(InvalidArgumentException::class, 'Route resource "params" (or its alias "parameters") must be an array.');

it('throws exception when model resource is missing class', function (): void {
    $manager = new ShareLinkManager();

    $manager->create([
        'type' => 'model',
        'id' => 123,
    ]);
})->throws(InvalidArgumentException::class, 'Model resource must have a non-empty "class" key.');

it('throws exception when model resource is missing id', function (): void {
    $manager = new ShareLinkManager();

    $manager->create([
        'type' => 'model',
        'class' => 'App\\Models\\Post',
    ]);
})->throws(InvalidArgumentException::class, 'Model resource must have a non-null "id" key.');

it('throws exception when array resource has invalid type', function (): void {
    $manager = new ShareLinkManager();

    $manager->create([
        'type' => 'invalid',
    ]);
})->throws(InvalidArgumentException::class, 'Array resource type must be "route" or "model". Got: invalid');
