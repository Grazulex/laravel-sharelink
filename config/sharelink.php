<?php

declare(strict_types=1);

return [
    'route' => [
        'prefix' => 'share',
        'middleware' => [
            Grazulex\ShareLink\Http\Middleware\EnsureShareLinkIsValid::class,
        ],
    ],
    'management' => [
        'enabled' => env('SHARELINK_MANAGEMENT_ENABLED', true),
        'middleware' => [
            // e.g., 'web', 'auth' — left empty by default for tests
        ],
    // Optional Gate ability to authorize management actions (revoke/extend) against the ShareLink model
    // Example: set to 'manage-sharelinks' and define Gate::define('manage-sharelinks', fn ($user, $link) => ...)
    'gate' => env('SHARELINK_MANAGEMENT_GATE', null),
    ],
    'signed' => [
        // If enabled, helper methods will generate signed URLs; when required, access must be signed
        'enabled' => env('SHARELINK_SIGNED_ENABLED', true),
        'required' => env('SHARELINK_SIGNED_REQUIRED', false),
        // Default TTL in minutes for temporary signed URLs
        'ttl' => env('SHARELINK_SIGNED_TTL', 15),
    ],

    'burn' => [
        // If enabled, links marked as burn-after-reading will be revoked immediately after first successful access
        'enabled' => env('SHARELINK_BURN_ENABLED', true),
        // If true, auto-treat max_clicks = 1 as burn-after-reading even without metadata flag
        'auto_max_clicks' => env('SHARELINK_BURN_AUTO', false),
        // Strategy can be 'revoke' (default) or 'delete' (hard delete). We keep 'revoke' for audit + prune.
        'strategy' => env('SHARELINK_BURN_STRATEGY', 'revoke'),
        // Metadata key used to mark a link as burn-after-reading
        'flag_key' => 'burn_after_reading',
    ],

    'limits' => [
        'ip' => [
            // Arrays of IPs or CIDR blocks. If 'allow' is non-empty, only those IPs are allowed.
            // 'deny' is always enforced; per-link metadata can override/augment (metadata.ip_allow/ip_deny)
            'allow' => [],
            'deny' => [],
        ],
        'rate' => [
            'enabled' => env('SHARELINK_RATE_ENABLED', false),
            // Max attempts allowed within decay seconds per token
            'max' => env('SHARELINK_RATE_MAX', 60),
            'decay' => env('SHARELINK_RATE_DECAY', 60), // seconds
        ],
        'password' => [
            'enabled' => env('SHARELINK_PASSWORD_LIMIT_ENABLED', true),
            'max' => env('SHARELINK_PASSWORD_LIMIT_MAX', 5),
            'decay' => env('SHARELINK_PASSWORD_LIMIT_DECAY', 600), // seconds
        ],
    ],
    'delivery' => [
        // If true and serving local files, set X-Sendfile header instead of streaming
        'x_sendfile' => env('SHARELINK_X_SENDFILE', false),

        // If set, use X-Accel-Redirect with this internal location prefix (e.g., '/protected')
        'x_accel_redirect' => env('SHARELINK_X_ACCEL_REDIRECT', null),
    ],

    'schedule' => [
        'prune' => [
            'enabled' => env('SHARELINK_SCHEDULE_PRUNE', true),
            // Cron expression or aliases like '@daily'; defaults to 3:00 AM daily
            'expression' => env('SHARELINK_SCHEDULE_PRUNE_EXPRESSION', '0 3 * * *'),
            'description' => 'sharelink:prune',
        ],
    ],

    'observability' => [
        'enabled' => env('SHARELINK_OBSERVABILITY_ENABLED', true),
        'log' => env('SHARELINK_OBSERVABILITY_LOG', true),
        'metrics' => env('SHARELINK_OBSERVABILITY_METRICS', false),
        // no tokens/IPs in logs; only non-PII fields are included
    ],
];
