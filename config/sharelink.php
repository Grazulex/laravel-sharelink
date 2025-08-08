<?php

declare(strict_types=1);

return [
    'route' => [
        'prefix' => 'share',
        'middleware' => [
            Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid::class,
        ],
    ],
];
