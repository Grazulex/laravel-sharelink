# Configuration

Edit `config/sharelink.php`.

## Route
- `route.prefix` (default: `share`)
- `route.middleware`: includes `EnsureShareLinkIsValid` by default.

## Signed URLs
- `signed.enabled` (default: true)
- `signed.required` (default: false)
- `signed.ttl` minutes (default: 15)

## Limits
- Rate limiting per token (middleware-enforced)
  - `limits.rate.enabled` (default: false)
  - `limits.rate.max`, `limits.rate.decay`
- Password attempt throttling
  - `limits.password.enabled` (default: true)
  - `limits.password.max`, `limits.password.decay`
- IP filtering
  - `limits.ip.allow` / `limits.ip.deny` arrays of IPs or CIDR blocks
  - Per-link overrides via `metadata.ip_allow` / `metadata.ip_deny`

## Burn after reading
- `burn.enabled` (default: true)
- `burn.auto_max_clicks` (treat `max_clicks=1` as burn)
- `burn.strategy` (`revoke` or `delete`)
- `burn.flag_key` metadata key

## Delivery
- `delivery.x_sendfile` (bool)
- `delivery.x_accel_redirect` (internal location prefix)

## Management endpoints
- `management.enabled` (default: true)
- `management.middleware` (e.g., `auth`)

## Scheduler
- `schedule.prune.enabled` (default: true)
- `schedule.prune.expression` (cron)
