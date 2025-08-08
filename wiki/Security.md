# Security Guide

## Passwords
- Always hashed (bcrypt/argon).
- Access requires `password` query; failures return standardized JSON.
- Throttled via `limits.password`.

## Signed URLs
- Enable via `signed.enabled`; require via `signed.required`.
- Helper `ShareLinkManager::signedUrl()` generates temporary signed URLs.

## Rate limiting
- Per-token+IP in middleware when `limits.rate.enabled`.

## IP filtering
- Global allow/deny in config; per-link overrides via metadata `ip_allow`/`ip_deny`.
- Supports IPv4 exact and CIDR.

## Burn after reading
- Enable `burn.enabled`.
- Flag with builder or auto with `max_clicks=1` if `burn.auto_max_clicks`.
- Strategy `revoke` (default) or `delete`.

## Do not leak PII
- Error responses avoid sensitive details.
- Avoid logging secrets/token in plaintext.
