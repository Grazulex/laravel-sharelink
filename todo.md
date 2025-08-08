# Laravel ShareLink – TODO / Roadmap

Source of truth for scope: see `content.txt`.

## MVP (core) – target: v1.0.0

- [x] Package metadata and namespaces aligned to `grazulex/laravel-sharelink`
- [x] Database migration `share_links`
  - [x] Columns: id (uuid), resource (json/string), token, password (nullable, hash), expires_at, max_clicks (nullable), click_count
  - [x] Audit: first_access_at, last_access_at, last_ip
  - [x] Revocation: revoked_at
  - [x] Indexes: token(unique), expires_at, revoked_at
- [x] Eloquent model `Models/ShareLink`
  - [x] Token auto-generation
  - [x] Accessors: isExpired(), isRevoked()
  - [x] Usage tracking: incrementClicks(), markAccessed(ip)
  - [x] Typed casts and PHPDoc for static analysis
- [x] Service `Services/ShareLinkManager`
  - [x] Builder-style API: create()->expiresIn()->maxClicks()->withPassword()->metadata()->generate()
  - [x] URL resolver for `/share/{token}`
- [x] Facade `Facades/ShareLink`
- [x] Route + Middleware + Controller
  - [x] GET `{prefix}/{token}` with configurable middleware stack
  - [x] Middleware validation: token existence, expiration, revocation, usage limit
  - [x] Controller: optional password gate (401), increments clicks, returns JSON or file download
  - [x] Audit updates (first/last access, IP)
- [x] Config `config/sharelink.php` (prefix, middleware)
- [x] Prune command `sharelink:prune` (expired + revoked, optional `--days`)
- [x] Tests (Pest)
  - [x] Manager unit test (URL + password hashing)
  - [x] Feature tests: expiration(410), quota(429), password(401/200), clicks+audit, prune
- [x] Tooling
  - [x] Pint config (strict types, imports, ordered elements)
  - [x] PHPStan + Larastan (level 5, 0 errors)
  - [x] Rector configured (safe sets)
  - [x] Composer scripts: pint, phpstan, rector, test, full

## v1.1 – Delivery & API polish

- [ ] File delivery improvements
  - [x] Stream via Storage (private disks)
  - [x] Set headers (Content-Type, Cache-Control) for local and Storage streams
  - [x] Set Content-Length where available (Storage drivers)
  - [x] Support Range requests for large files (local files)
  - [x] Optional X-Sendfile / X-Accel-Redirect integration (config flags)
  - [ ] S3: generate temporary signed URLs when configured (Deferred)
- [ ] JSON API Resources for consistent responses
  - [x] Standard error format: { status, code, title, detail }
  - [x] Content negotiation (Accept: application/json)
  - [x] Success responses via API Resources
- [ ] Revocation API
  - [x] Service method to revoke
  - [x] Service method to extend links
  - [ ] Optional HTTP endpoint(s) guarded by policies
- [x] Events for extensibility
  - [x] ShareLinkCreated
  - [x] ShareLinkAccessed
  - [x] ShareLinkRevoked
  - [x] ShareLinkExpired (emitted on expired access and during prune)

## v1.2 – Security & Limits

- [ ] Optional Laravel signed routes (in addition to tokens)
- [ ] Per-link IP allow/deny lists
- [ ] Rate limiting per token (throttle middleware integration)
- [ ] One-time “burn after reading” links (max_clicks=1 + immediate prune)
- [ ] Password attempt throttling (avoid brute force)

## v1.3 – DevEx & Ops

- [ ] Scheduler wiring for prune command (daily by default, configurable)
- [ ] Additional Artisan commands: create, revoke, list
- [ ] Observability
  - [ ] Optionally log access events (without PII) and expose metrics hooks
- [ ] Config hardening & docs for production

## v1.4 – Admin UI (optional)

- [ ] Nova/Filament widgets: list, create, revoke, analytics

## Tests – matrix to cover

- [ ] Resources
  - [x] File on local/private disk (headers validated)
  - [ ] Route target
  - [ ] Model preview (morph target)
- [ ] Expiration: absolute time vs duration
- [ ] Concurrency on max_clicks (transaction/locking)
- [ ] JSON vs browser flows (password prompt handling)
- [ ] Storage drivers (local, s3) – mocked
 - [x] Events dispatch on created/accessed/revoked/expired

## Documentation

- [ ] README: install, config, quickstart, examples (file/route/model)
- [ ] Security guide (hashing, tokens, throttling, no PII leaks)
- [ ] API reference (Facade/Manager, middleware, commands)
- [ ] CHANGELOG & versioning matrix

## Nice-to-have

- [ ] DTOs for resource representation (file, route, model) instead of raw arrays
- [ ] Policy examples / gates for management actions
- [ ] Sample blades for password prompt (when not using JSON clients)

## Acceptance criteria (MVP)

- All validations enforced by middleware
- Passwords stored hashed (bcrypt/Hash::make)
- Audit fields updated on access
- Prune command deletes expired/revoked as expected
- “composer full” passes locally (Pint, PHPStan, Rector, Tests)
