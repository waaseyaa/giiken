# Pre-upgrade Baseline — Upgrade Waaseyaa to alpha.173

**Captured:** 2026-05-06T16:44:41Z
**Captured by:** WP01 (T001 + T002 + T003)
**Project root:** /home/jones/dev/giiken
**Upstream symlink target:** /home/jones/dev/waaseyaa @ v0.1.0-alpha.173 (HEAD 38feb0fbe)

> **Note on path-repo state.** `vendor/waaseyaa/*` is a Composer path repository, so the running app is already executing the upstream working tree (which is at `v0.1.0-alpha.173`). `composer.json` still pins `^0.1.0-alpha.145`. `composer.lock` records each package as `dev-main`. This baseline therefore captures the green state with the new framework code already loaded; the mission's remaining work is to bump the constraint strings in `composer.json` to match the framework that is already in use.

## PHPUnit

- Exit code: 0
- Tests: 258
- Assertions: 807
- Wall time: 00:01.922
- Notes: Suite reports `OK, but there were issues!` with 2 deprecation notices; suite is green (exit 0). Project `CLAUDE.md` mentions 238/238 — the actual current count is 258.

## PHPStan level 8 (`src/`, `tests/`)

- Exit code: 1
- Findings: 45 (pre-existing baseline)
- Notes: Per WP T002, pre-existing findings are acceptable as a baseline; the upgrade gate becomes "no new findings" rather than "zero findings". Sample findings include `KnowledgeItemRepositoryInterface` parameter type mismatches in `App\Export\ExportService`, `App\Query\SynthesisService`, and `App\Pipeline\CompilationPipeline` constructors, plus a `Call to an undefined method object::save()` in `tests/Integration/Entity/ContentEntitySqlIntegrationTest.php:533`. These exist before any constraint change and must remain at or below 45 after the bump.

## Boot-to-browser smoke

- `./vendor/bin/waaseyaa migrate`: `Ran 6 migrations.` (5 framework migrations under `waaseyaa/queue:*` plus `app:20260418_150000_add_unique_index_to_community_slug`). Three pre-existing PHP `include()` warnings emitted from `vendor/composer/ClassLoader.php` for stale paths (`tests/Unit/Http/Middleware/RequireStaffRoleTest.php`, `waaseyaa/api/src/JsonResponseTrait.php`); benign — autoloader fall-through does not block boot.
- `./vendor/bin/waaseyaa giiken:seed:test-community`: **command not found** — output `There are no commands defined in the "giiken:seed" namespace.` The console command referenced in `CLAUDE.md` and in this WP's Phase A status block is not registered in the current build. Recorded verbatim as the baseline; no synthetic seed substituted. The community used by `/test-community` (`Sagamok Anishnawbek`, slug `sagamok-anishnawbek`) was already present in the SQLite database from prior development runs.
- `curl -i http://127.0.0.1:8080/`: HTTP 200, identified as `"component":"Discover"` Inertia page (props include the `Sagamok Anishnawbek` community), latency 0.123s — well under the 2s NFR-003 threshold.
- `curl -i http://127.0.0.1:8080/test-community`: HTTP 200, identified as `"component":"Discovery\/Index"` Inertia page. Props show `community: null, recentItems: { items: [], totalHits: 0, totalPages: 0 }` — the slug `test-community` does not match any seeded community (because the seed command does not exist); the route resolves and renders the empty-state shell. Latency 0.014s — well under the 2s NFR-003 threshold.

## Post-bump failure surface

_To be recorded in `baseline-postbump.md` (sibling file) by WP02 T007 after the composer bump._
