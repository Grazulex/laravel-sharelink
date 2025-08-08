
> Copilot: always generate code with strict types, full return types, no unused imports, no dead code, and keep public APIs small and intentional.

---

## 2) Dependencies & Tooling Matrix

- **Composer (dev):** Pint, Pest 3.x, `pest-plugin-laravel`, Larastan 3.x, Rector 2.x, Doctrine DBAL for migrations, Orchestra Testbench 9/10.  
- **Runtime:** `illuminate/support` 11/12 compatible.

> See the project `composer.json` for exact constraints and scripts. (CI uses `composer run-script` for `pint`, `phpstan`, `rector`, `test`.)

---

## 3) Folder & Architecture

```
/config/          # publishable config
/database/migrations/  # publishable migrations
/routes/          # minimal; keep controllers thin
/src/
  Contracts/      # interfaces
  DTO/            # immutable data transfer objects
  Exceptions/
  Facades/
  Http/
    Controllers/
    Middleware/
    Requests/     # if needed
  Models/
  Policies/
  Services/       # orchestration / domain services
  Support/        # helpers / macros (internal)
  Traits/
tests/
  Feature/
  Unit/
```

**Principles**  
- SOLID throughout; depend on **Contracts**, not concretions.  
- **Thin Controllers**; push logic to **Services/Actions**.  
- Use **DTOs** (immutable) for cross-layer data — avoid nested arrays.  
- Keep **Models** focused (relations, casts, scopes).  
- Configurable behavior via config + container bindings (SRP/OCP).  
- Expose a minimal, stable **public surface**; mark internals with `@internal`.

---

## 4) Code Style (Pint)

Use the project Pint rules. Notable bits to respect automatically:
- `declare_strict_types` and `fully_qualified_strict_types`
- enforce `strict_comparison`
- import classes/functions/constants via `global_namespace_import`
- prefer `protected_to_private` where possible
- keep ordered class elements, interfaces, and traits
- **Note:** this project **does not enforce `final_class`** globally (it is disabled).

> Copilot: format code to match these Pint rules out‑of‑the‑box; do not suggest `final` on every class—reserve it for internal details and DTOs only.

---

## 5) Static Analysis (PHPStan)

- Aim for **max level** with Larastan enabled.  
- Typed properties and return types everywhere.  
- Prefer generics-aware docblocks only for complex shapes (`@phpstan-type`, `@template`), but favor DTOs over associative arrays.  
- Treat PHPDoc certainty as real types when safe; remove dead code.

---

## 6) Rector (safe refactors)

- Use prepared sets: **deadCode**, **codeQuality**, **typeDeclarations**, **privatization**, **earlyReturn**, **strictBooleans**.  
- Run in CI with `--dry-run`; commit only reviewed diffs.  
- Let Rector suggest small, incremental improvements (typed params/returns, early returns, privatize where safe).

---

## 7) Testing with Pest 3

**Conventions**
- Organize tests with `describe('<subject>')` and `it('<does something>')`.  
- Use **datasets** to cover variations.  
- **Feature tests** validate the public API & routes; **unit tests** cover small pure services.  
- Use factories for models; mock I/O (filesystem, HTTP).  
- Strive for ≥90% coverage on services and critical flows.

**Example**
```php
<?php

use Vendor\Package\Services\ShareLinkManager;
use Illuminate\Support\Facades\Hash;

describe('ShareLinkManager', function () {
    it('creates an expiring link', function () {
        $link = app(ShareLinkManager::class)
            ->create('/tmp/demo.pdf')
            ->expiresIn(2)
            ->maxClicks(3)
            ->withPassword('secret')
            ->generate();

        expect($link->token)->toBeString()
            ->and($link->expires_at)->not->toBeNull()
            ->and(password_get_info($link->password)['algo'])->toBeTruthy();
    });
});
```

---

## 8) Testbench Workbench

- Register the package service provider in `testbench.yaml`.  
- Use workbench app for end‑to‑end flows without scaffolding a real Laravel app.  
- Keep tests deterministic; no real network calls.

---

## 9) HTTP & APIs

- Controllers are **thin**; validate via Form Requests or DTO validators.  
- For JSON, return **API Resources** (avoid raw arrays).  
- Consistent error format (`status`, `code`, `title`, `detail`).  
- Add rate limits on public endpoints when applicable.

---

## 10) Eloquent & Database

- Migrations are **reversible** and explicit.  
- Add indexes for FKs and frequently filtered columns.  
- Avoid N+1; eager load in services.  
- Throw domain **Exceptions** rather than returning `null` on failures.

---

## 11) Security Checklist

- Validate and sanitize all inputs.  
- Prefer **signed URLs or tokens** for public links; hash secrets (bcrypt/argon2id).  
- Do not leak sensitive data in logs or exceptions.  
- Policies/guards apply at the boundary; services remain framework-agnostic.

---

## 12) CI & Scripts

Run locally and in CI (same order):
```bash
composer run pint
composer run phpstan
composer run rector -- --dry-run
composer run test
```

---

## 13) Copilot Prompting Rules (what to suggest)

- Interfaces + dependency injection at boundaries.  
- DTOs instead of nested arrays.  
- Small composable methods; prefer early returns.  
- Pest `describe/it` + datasets in examples.  
- Respect Pint imports & ordering.  
- Offer API Resources and Policies where useful.

**Avoid suggesting**  
- God classes, long controllers, static state.  
- Catch‑all try/catch without context/rethrow.  
- Tight coupling of domain services to HTTP or facades (OK at app boundary).

---

## 14) Release & Docs

- SemVer with CHANGELOG.  
- README: install, config, quickstart, examples, FAQ, version matrix.  
- Wiki for advanced recipes and extension points.  
- Keep samples copy‑pasteable and tested where feasible.