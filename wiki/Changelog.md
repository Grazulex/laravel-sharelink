# Changelog

All notable changes to this project will be documented here.

## v1.2.0 – Security & Limits
- Optional Laravel signed routes (helper + middleware validation)
- Per-token rate limiting (middleware)
- Password attempt throttling
- IP allow/deny lists (config + per-link metadata, IPv4/CIDR)
- Burn-after-reading (revoke/delete on first access)
- Scheduler wiring for `sharelink:prune`

## v1.1.0 – Delivery & API polish
- Storage streaming with headers
- Local file headers + Content-Length
- HTTP Range support for local files
- X-Sendfile / X-Accel-Redirect support
- JSON API Resources and content negotiation
- Management endpoints (revoke/extend)

## v1.0.0 – MVP
- ShareLink model, migration, and manager
- Validation middleware and controller (password gate)
- Events (created/accessed/revoked/expired)
- Prune command
- Pest tests and tooling (Pint, PHPStan, Rector)
