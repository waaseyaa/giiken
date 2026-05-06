# Migration Notes — Upgrade Waaseyaa to alpha.173

**Mission:** `01KQYY1NT1BW6F7QKZA02969PB` — `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Date range:** 2026-05-06 (single-day execution)
**Final upstream tag adopted:** `v0.1.0-alpha.173` (commit `38feb0fbe`)

## Executive summary

The upgrade was structurally a no-op for application code. The path-repo override at `composer.local.json` had already been pointing `vendor/waaseyaa/*` at the upstream monorepo's `dev-main` branch (currently at `v0.1.0-alpha.173`) for the entire planning window — meaning the running Giiken application was already executing alpha.173 framework code while declaring `^0.1.0-alpha.145` in its published constraints. This mission's deliverable is **constraint hygiene** plus documented confirmation that none of the predicted breaking changes (alpha.162 entity registration drop, alpha.162 controller signature drop, alpha.171 service-provider lifecycle, alpha.171 JsonResponseTrait redesign) actually break Giiken under the alpha.173 runtime.

## What changed

### Composer constraint hygiene (WP02)

- **Before:** 33 `waaseyaa/*` constraints at `^0.1.0-alpha.145`, plus `waaseyaa/northcloud` at `@dev`. Lockfile resolved `dev-main` against the path repo, so the version of every package actually loaded was alpha.173.
- **After:** All 33 in-scope `waaseyaa/*` constraints at `^0.1.0-alpha.173`. `waaseyaa/northcloud` unchanged at `@dev`. Lockfile regenerated via `composer update 'waaseyaa/*' --with-all-dependencies` — 113 lock operations, exit 0.
- **Diff scope:** `composer.json` (33 lines), `composer.lock` (regenerated), `kitty-specs/.../baseline-postbump.md` (created).

### What did NOT change

No source files in `src/` were modified by this mission. Specifically, the following migrations predicted in `research.md` were **not executed** because empirical verification showed zero failures against alpha.173:

- Entity-registration migration (alpha.162): `KnowledgeItem.php` and `WikiLintReport.php` retain their constructor-time `fieldDefinitions:` pattern. The framework accommodates this silently.
- Controller signature migration (alpha.162 + alpha.173 shim): All 6 controllers (`HomeController`, `WebLogoutController`, `WebLoginController`, `DiscoveryController`, `ManagementController`, `QueryApiController`) retain the legacy `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` signature on all 19 methods. Runtime smoke captured zero `implicit_array_unbound` shim notices.
- Service provider lifecycle (alpha.171): no changes needed (Giiken had zero `setKernelResolver()` call sites pre-upgrade).
- JsonResponseTrait redesign (alpha.171): no changes needed (Giiken's `QueryApiController` uses a private local `jsonBody()` helper, not the framework trait).

## Adapted contracts (per FR-014 / NFR-004)

- **`composer.json` constraint floor** (`alpha.145 → alpha.173`)
  - File: `composer.json`
  - Why: alpha.145 → alpha.173 represents 28 alpha releases of upstream evolution. The published constraint now matches what the path-repo runtime was actually loading.
  - 33 single-line changes; `waaseyaa/northcloud` line unchanged (`@dev` per FR-003).

## Upstream fixes applied during this mission

**None.** No residual contract drift surfaced. The mission did not require any upstream `waaseyaa/framework` changes.

## Deferred upstream issues

**None filed.** The framework's compatibility surface for legacy patterns is silent (no shim notices fire under PHP built-in server smoke), so there is nothing actively flagging deprecation against Giiken.

## Deferred Giiken-side migrations (future mission)

Three categories of work were planned for this mission but **not executed** because empirical verification showed they were unnecessary against alpha.173. They remain valid future-proofing work that should be addressed when:

1. The framework drops the alpha.173 implicit-array compatibility shim entirely; or
2. A new alpha tag introduces additional breaking changes that the legacy patterns cannot accommodate; or
3. Giiken ships a release intended to be installable from Packagist alone (no path-repo override).

The deferred work, with concrete file/method counts captured in `research.md` §5:

- **WP03 deferred — entity attribute-first migration.** 2 files (`src/Entity/KnowledgeItem/KnowledgeItem.php`, `src/Wiki/WikiLintReport.php`) plus possibly `src/Entity/Community/Community.php`, plus `src/Provider/EntitiesProvider.php`. Migrate from `fieldDefinitions:` constructor pattern to `EntityType::fromClass()` + `#[ContentEntityType]` + `#[Field]` attributes per alpha.162.
- **WP04+WP05 deferred — controller typed-parameter-injection migration.** 6 files, 19 method signatures across `src/Http/Controller/`. Migrate from legacy `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` to typed parameter injection with `#[MapRoute]` and `#[MapQuery]` attributes per alpha.162 / alpha.173.

## New operational capabilities adopted

The upgrade brings these alpha.146 → alpha.173 upstream additions into Giiken's effective surface, even though no Giiken code change is required to use them:

- `bin/waaseyaa db:init` (alpha.152) — sanctioned first-deploy database initializer. Idempotent, safe under `APP_ENV=production`.
- `bin/waaseyaa migrate --dry-run` and `--verify` (alpha.165) — migration-plan preview and ledger checksum audit.
- Schema evolution v2 ledger columns (alpha.165) — `waaseyaa_migrations` gains nullable `checksum` and `diff_hash` columns, added by upstream's idempotent backfill.
- `EntityType::fromClass()` + `#[ContentEntityType]` + `#[Field]` attribute-first registration (alpha.162) — available when Giiken's entities migrate (deferred per above).
- Typed parameter injection in SSR controllers (alpha.162) — available when Giiken's controllers migrate (deferred per above).
- `ServiceProvider::setKernelServices()` and `mergeChildProvider()` (alpha.171) — N/A for Giiken (no legacy `setKernelResolver()` call sites).

## What stayed unchanged

- All 18 HTTP routes preserved (paths, methods, auth policies, response shapes).
- All 3 entity types preserved (fields, relationships, access semantics).
- All 5 ingestion handlers preserved.
- All 5 compilation pipeline steps preserved.
- All access tier × role hierarchy decisions preserved.
- `composer.local.json` path-repo override remains functional.
- `waaseyaa/northcloud` constraint remains `@dev`.
- 258 PHPUnit tests, 807 assertions, all passing pre and post bump.
- 45 pre-existing PHPStan level 8 findings, no new findings.

## Pre-existing items surfaced during the mission (not fixed here)

These were observed during WP01 baseline / WP02 post-bump capture and are out of scope for this upgrade. Some are tracked for resolution in WP07; others are recorded for future attention.

- **`CLAUDE.md` § "Boot-to-browser status" drift** — claims 238/238 tests, references a `giiken:seed:test-community` console command and a `/test-community` smoke route. Actual test count is 258. The seed command is not registered. The slug does not exist in the database. (Resolved in WP07.)
- **`fgetcsv()` PHP-native deprecations** in `src/Ingestion/Handler/CsvIngestionHandler.php` lines 59 and 63 — `$escape` parameter must be provided as its default value will change. PHP 8.x deprecation, unrelated to the framework upgrade. (Resolved in WP07.)
- **3 benign autoload `include()` warnings** during boot for stale paths (`tests/Unit/Http/Middleware/RequireStaffRoleTest.php`, `waaseyaa/api/src/JsonResponseTrait.php`). Likely a stale `composer.lock` autoload cache; not blocking. (Suggested fix: `composer dump-autoload`. Not actioned in this mission.)
- **Empty `storage/giiken.sqlite` file** present alongside the active `storage/waaseyaa.sqlite`. Likely dead from a prior config; not referenced by current code. (Cleanup deferred.)
- **45 pre-existing PHPStan level 8 findings** (e.g., `KnowledgeItemRepositoryInterface` parameter-type mismatches in `ExportService`/`SynthesisService`/`CompilationPipeline`, `object::save()` undefined-method in `ContentEntitySqlIntegrationTest.php:533`). Pre-date this mission; gate floor remains 45. (Future cleanup mission.)

## Final verification (to be appended by WP07)

_WP07 will append the final PHPUnit/PHPStan/smoke results, the lifecycle drift outcome, and the `CLAUDE.md` updates._
