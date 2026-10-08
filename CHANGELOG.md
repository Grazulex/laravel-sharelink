# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v1.4.0] - 2026-10-08

### Changed
- **Minimum PHP version is now 8.4**: PHP 8.3 is no longer supported (#17)
- CI test matrix now runs PHP 8.4 and 8.5 (#17)
- `docker-compose.yml` development container now uses PHP 8.4 (#17)
- Rector configuration updated for Rector 2.x (the removed `strictBooleans` prepared set was breaking `vendor/bin/rector`); Rector applied to `src/`.
- GitHub Actions bumped: `actions/checkout` v4 -> v5, `softprops/action-gh-release` v1 -> v2.

### Removed
- Duplicate `Code Quality` workflow (superseded by the `Code Style` and `Static Analysis` workflows).

## [v1.3.0] - 2026-09-17

### Added
- Laravel 13 support (`illuminate/support` `^12.0|^13.0`, `orchestra/testbench` `^10.0|^11.0`).
- Dedicated `Code Style` (Pint) and `Static Analysis` (PHPStan) GitHub Actions workflows.

### Changed
- Minimum PHP version is now 8.3 (explicitly enforced across the CI matrix).
- Development dependencies updated: Pest `^3.8|^4.0`, Pest Laravel plugin `^3.2|^4.0`, Pint `^1.24`.
- CI test matrix now covers PHP 8.3 / 8.4 with Laravel 12 / 13 (prefer-lowest and prefer-stable).
- Release workflow validates the package against Laravel 13.

### Removed
- Laravel 11 support (end of life).

## [v1.2.0] - 2025-10-13

### Fixed
- `ShareLink::create()` now accepts array resources (#7).

## [v1.1.0] - 2025-08-20

### Changed
- Documentation and repository housekeeping.

## [v1.0.0] - 2025-08-08

### Added
- Initial release: temporary share links for files, routes and model previews with expiration, click limits, password protection, rate limiting, IP filtering, signed URLs, burn-after-reading, auditing and Artisan commands.

[v1.4.0]: https://github.com/Grazulex/laravel-sharelink/compare/v1.3.0...v1.4.0
[v1.3.0]: https://github.com/Grazulex/laravel-sharelink/compare/V1.2.0...v1.3.0
[v1.2.0]: https://github.com/Grazulex/laravel-sharelink/compare/v1.1.0...V1.2.0
[v1.1.0]: https://github.com/Grazulex/laravel-sharelink/compare/v1.0.0...v1.1.0
[v1.0.0]: https://github.com/Grazulex/laravel-sharelink/releases/tag/v1.0.0
