<?php

declare(strict_types=1);

return [
    'route' => [
        'prefix' => 'share',
        'middleware' => [
            Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid::class,
        ],
    ],
    'delivery' => [
        // If true and serving local files, set X-Sendfile header instead of streaming
        'x_sendfile' => env('SHARELINK_X_SENDFILE', false),

        // If set, use X-Accel-Redirect with this internal location prefix (e.g., '/protected')
        'x_accel_redirect' => env('SHARELINK_X_ACCEL_REDIRECT', null),

        // S3 temporary URL TTL in minutes when using a disk that supports temporaryUrl
        's3_ttl' => env('SHARELINK_S3_TTL', 5),
    ],
];
