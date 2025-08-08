# Laravel ShareLink

Generate, manage, and secure temporary share links for files, routes, and model previews.

- Laravel: 11.x, 12.x
- PHP: 8.3+

Quick links
- [[Install]] · [[Configuration]] · [[Quickstart]] · [[Endpoints]] · [[API]] · [[Security]] · [[Events]] · [[CLI]]

## Features
- Tokenized share links with expiration and click limits
- Optional password protection (hashed)
- Signed URLs support (optional/required)
- Per-token rate limiting and password attempt throttling
- IP allow/deny lists (global and per-link metadata)
- Burn-after-reading (auto revoke/delete after first access)
- File delivery: local, Storage streaming, Range support, X-Sendfile/X-Accel
- Management endpoints: revoke/extend (configurable)
- Standardized JSON error format; API Resources on success
- Events for created/accessed/revoked/expired
- Prune command and scheduler wiring

## Version matrix
- Laravel 11/12 supported (via illuminate/support)
- Testbench 9/10 for package testing

> See [[Install]] to get started.
