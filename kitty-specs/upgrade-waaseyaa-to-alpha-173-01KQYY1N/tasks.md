# Tasks: Upgrade Waaseyaa to alpha.173

**Mission:** `01KQYY1NT1BW6F7QKZA02969PB` — `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Plan:** [plan.md](./plan.md) · **Spec:** [spec.md](./spec.md) · **Research:** [research.md](./research.md)
**Branch:** `main` (planning base = merge target, trunk-based)
**Date:** 2026-05-06

---

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Capture PHPUnit baseline (test count, exit, wall time) | WP01 | [P] |
| T002 | Capture PHPStan level 8 baseline finding count | WP01 | [P] |
| T003 | Capture boot-to-browser smoke baseline (migrate, seed, serve, curl) | WP01 |  |
| T004 | Commit `baseline.md` with all three captured baselines | WP01 |  |
| T005 | Edit `composer.json` — rewrite 38 `waaseyaa/*` constraints to `^0.1.0-alpha.173` | WP02 |  |
| T006 | Run `composer update 'waaseyaa/*' --with-all-dependencies` to regenerate lockfile | WP02 |  |
| T007 | Run PHPUnit + PHPStan post-bump; record failure surface in `baseline-postbump.md` | WP02 |  |
| T008 | Commit `composer.json`, `composer.lock`, and `baseline-postbump.md` | WP02 |  |
| T009 | Migrate `KnowledgeItem.php` to `#[ContentEntityType]` + `#[Field]` attribute-first | WP03 | [P] |
| T010 | Migrate `WikiLintReport.php` to attribute-first registration | WP03 | [P] |
| T011 | Audit `Community.php` against post-bump errors; migrate if needed | WP03 |  |
| T012 | Switch `EntitiesProvider` (and `AppServiceProvider` if used) to `EntityType::fromClass()` | WP03 |  |
| T013 | Update test fixtures using raw `EntityType` shapes to `TestEntityType::stub()` | WP03 |  |
| T014 | Run PHPUnit; verify zero entity-registration-class failures | WP03 |  |
| T015 | Migrate `HomeController::discover()` to typed parameter injection | WP04 | [P] |
| T016 | Migrate `WebLogoutController::logout()` to typed parameter injection | WP04 | [P] |
| T017 | Migrate `WebLoginController::showForm()` and `submit()` to typed parameter injection | WP04 | [P] |
| T018 | Migrate `DiscoveryController` (5 methods) to typed parameter injection | WP04 |  |
| T019 | Migrate `ManagementController` (7 methods) to typed parameter injection | WP05 |  |
| T020 | Migrate `QueryApiController` (3 methods) to typed parameter injection | WP05 |  |
| T021 | Run smoke + PHPUnit + log capture; verify zero `implicit_array_unbound` notices | WP05 |  |
| T022 | Run full PHPUnit + PHPStan + smoke; identify residual failures not predicted in research.md | WP06 |  |
| T023 | For each residual failure: apply upstream fix in `waaseyaa/framework`, push tag, bump alpha tag if needed | WP06 |  |
| T024 | Document each upstream-fix action and any deferred upstream issues in `migration-notes.md` | WP06 |  |
| T025 | Run final PHPUnit; verify zero failures, count ≥ baseline | WP07 |  |
| T026 | Run final PHPStan level 8; verify zero new findings | WP07 |  |
| T027 | Run final boot-to-browser smoke; verify 200/200 with seeded items, latency < 2s | WP07 |  |
| T028 | Run `scripts/check-lifecycle-drift.sh`; update `docs/architecture/lifecycle.md` if drift detected | WP07 |  |
| T029 | Finalize `migration-notes.md` per FR-014 / NFR-004 | WP07 |  |
| T030 | Update `CLAUDE.md` § "Boot-to-browser status" with new alpha tag and migration date | WP07 |  |

`[P]` in this index marks tasks that are file-independent within their work package (different files, different concerns) and can be executed concurrently if a multi-agent flow is desired. The Subtask Index is reference-only; per-WP progress is tracked via the checkboxes inside each WP section below.

---

## Phase 1 — Pre-upgrade Baseline (Setup)

### WP01 — Pre-upgrade baseline capture

- **Goal:** Lock in green-state baseline values (PHPUnit, PHPStan, smoke) before any constraint changes.
- **Priority:** P0 (blocker for every later phase)
- **Independent test:** `baseline.md` is committed and shows zero failures across PHPUnit, PHPStan level 8, and the boot-to-browser smoke path.
- **Risks:** If the baseline is not green, the upgrade cannot start; surfaces pre-existing issues that must be triaged before proceeding.
- **Dependencies:** none.
- **Estimated prompt size:** ~280 lines.
- **Prompt:** [tasks/WP01-pre-upgrade-baseline.md](./tasks/WP01-pre-upgrade-baseline.md)

**Tracking:**

- [ ] T001 Capture PHPUnit baseline (WP01)
- [ ] T002 Capture PHPStan level 8 baseline (WP01)
- [ ] T003 Capture boot-to-browser smoke baseline (WP01)
- [ ] T004 Commit `baseline.md` with all three captures (WP01)

---

## Phase 2 — Composer Constraint Bump (Foundational)

### WP02 — Composer constraint bump

- **Goal:** Bump all 38 in-scope `waaseyaa/*` constraints to `^0.1.0-alpha.173`, regenerate the lockfile, and capture the post-bump failure surface to confirm research.md predictions.
- **Priority:** P0 (foundation for all migration WPs)
- **Independent test:** `composer.json` shows `^0.1.0-alpha.173` for all 38 in-scope packages; `composer.lock` regenerated and committed; `baseline-postbump.md` records the post-bump failure surface enumerating which categories of test failures appeared.
- **Risks:** Composer fails to resolve (path repo handles this; verify); transitive conflict from `waaseyaa/northcloud@dev`; failure surface diverges materially from research.md predictions.
- **Dependencies:** WP01.
- **Estimated prompt size:** ~320 lines.
- **Prompt:** [tasks/WP02-composer-bump.md](./tasks/WP02-composer-bump.md)

**Tracking:**

- [ ] T005 Edit `composer.json` — rewrite 38 constraints (WP02)
- [ ] T006 Run `composer update 'waaseyaa/*' --with-all-dependencies` (WP02)
- [ ] T007 Capture post-bump failure surface (WP02)
- [ ] T008 Commit `composer.json`, `composer.lock`, `baseline-postbump.md` (WP02)

---

## Phase 3 — Entity Registration Migration (Story Phase: alpha.162 contract adaptation)

### WP03 — Entity registration migration

- **Goal:** Migrate the 2-3 entity types (`KnowledgeItem`, `WikiLintReport`, possibly `Community`) and the providers that register them from constructor-time `fieldDefinitions:` to compile-time `#[ContentEntityType]` + `#[Field]` attributes consumed via `EntityType::fromClass()`.
- **Priority:** P1 (alpha.162 breaking change — must clear before tests can pass)
- **Independent test:** No source file in `src/` matches the regex `fieldDefinitions:`; all entity-registration-class failures gone from PHPUnit; smoke path still surfaces seeded `test-community` knowledge items at `/test-community`.
- **Risks:** Field type inference produces unexpected types for backed enums or nullable fields; storage hints (`FieldStorage::Column` vs `FieldStorage::Data`) may need explicit declaration to match existing schema; test fixtures using raw `EntityType` shapes need migration to `TestEntityType::stub()`.
- **Dependencies:** WP02.
- **Estimated prompt size:** ~480 lines.
- **Prompt:** [tasks/WP03-entity-registration-migration.md](./tasks/WP03-entity-registration-migration.md)

**Tracking:**

- [ ] T009 Migrate `KnowledgeItem.php` to attribute-first (WP03)
- [ ] T010 Migrate `WikiLintReport.php` to attribute-first (WP03)
- [ ] T011 Audit `Community.php`; migrate if post-bump errors require (WP03)
- [ ] T012 Switch `EntitiesProvider` to `EntityType::fromClass()` (WP03)
- [ ] T013 Update test fixtures using raw `EntityType` shapes (WP03)
- [ ] T014 Verify zero entity-registration-class failures in PHPUnit (WP03)

---

## Phase 4 — Controller Migration (Story Phase: alpha.162/.173 contract adaptation)

### WP04 — Controller migration: Discovery + Auth

- **Goal:** Migrate the 4 simpler controllers (`HomeController`, `WebLogoutController`, `WebLoginController`, `DiscoveryController` — total 9 method signatures) from legacy `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` to typed parameter injection per alpha.162 with `#[MapRoute]` / `#[MapQuery]` attributes per alpha.173 shim guidance.
- **Priority:** P1 (alpha.162 breaking change; alpha.173 shim mitigates but project doctrine requires zero shim notices)
- **Independent test:** Migrated methods declare typed parameters (no unannotated `array $params` / `array $query`); discovery routes (`/`, `/{communitySlug}`, `/{communitySlug}/search`, `/{communitySlug}/ask`, `/{communitySlug}/item/{itemId}`) and auth routes (`/login` GET+POST, `/logout`) continue to dispatch correctly under smoke; zero `implicit_array_unbound` notices fired by these controllers.
- **Risks:** Route param name mismatch (e.g., the controller expected `$params['communitySlug']` but route declares `{slug}`); `DiscoveryController::show()` reads both `$params['communitySlug']` and `$params['itemId']` — both must migrate; query parameters in search/ask need `#[MapQuery]` typing.
- **Dependencies:** WP02.
- **Parallelism:** can parallel WP05 (different file ownership). Within WP04, T015–T017 can parallel each other (independent files).
- **Estimated prompt size:** ~420 lines.
- **Prompt:** [tasks/WP04-controller-migration-discovery-auth.md](./tasks/WP04-controller-migration-discovery-auth.md)

**Tracking:**

- [ ] T015 Migrate `HomeController::discover()` (WP04)
- [ ] T016 Migrate `WebLogoutController::logout()` (WP04)
- [ ] T017 Migrate `WebLoginController` (2 methods) (WP04)
- [ ] T018 Migrate `DiscoveryController` (5 methods) (WP04)

### WP05 — Controller migration: Management + API + verification

- **Goal:** Migrate `ManagementController` (7 methods) and `QueryApiController` (3 methods), then verify zero `implicit_array_unbound` deprecation notices across all 6 controllers.
- **Priority:** P1
- **Independent test:** No method in `src/Http/Controller/` uses unannotated `array $params` or `array $query`; smoke path covers `/{communitySlug}/manage*` and `/api/v1/{ask,report,synthesis}`; deprecation log capture during smoke shows zero `implicit_array_unbound` and zero shim notices.
- **Risks:** `ManagementController::ingestUpload()` handles file upload — typed param binding for uploaded file may differ from previous pattern; `QueryApiController` methods consume JSON request body via local `jsonBody()` helper, not framework trait — verify this pattern is still functional post-bump or migrate to `RequestData` DTO.
- **Dependencies:** WP02.
- **Parallelism:** can parallel WP04.
- **Estimated prompt size:** ~360 lines.
- **Prompt:** [tasks/WP05-controller-migration-management-api.md](./tasks/WP05-controller-migration-management-api.md)

**Tracking:**

- [ ] T019 Migrate `ManagementController` (7 methods) (WP05)
- [ ] T020 Migrate `QueryApiController` (3 methods) (WP05)
- [ ] T021 Verify zero `implicit_array_unbound` notices across all 6 controllers (WP05)

---

## Phase 5 — Residual Contract Drift Cleanup

### WP06 — Residual contract drift cleanup

- **Goal:** Surface and resolve any failure not predicted in research.md. Per project doctrine, residual contract drift is fixed **upstream in `waaseyaa/framework`**, not patched in Giiken.
- **Priority:** P2 (only fires if WP03–WP05 leave failures)
- **Independent test:** PHPUnit, PHPStan, and smoke are all green after WP06; `migration-notes.md` enumerates every upstream-fix action with framework PR/commit ref and every deferred upstream issue with a tracking link.
- **Risks:** Upstream fix takes longer than mission window allows (deferral path defined); residual is in `waaseyaa/northcloud@dev` (out of scope for this mission per FR-003).
- **Dependencies:** WP03, WP04, WP05.
- **Estimated prompt size:** ~280 lines.
- **Prompt:** [tasks/WP06-residual-drift-cleanup.md](./tasks/WP06-residual-drift-cleanup.md)

**Tracking:**

- [ ] T022 Identify residual failures via PHPUnit + PHPStan + smoke (WP06)
- [ ] T023 Apply upstream fixes for each residual; bump alpha tag if cut (WP06)
- [ ] T024 Document upstream-fix actions and deferrals in `migration-notes.md` (WP06)

---

## Phase 6 — Final Verification + Lifecycle Sync (Polish)

### WP07 — Final verification + lifecycle sync

- **Goal:** Run the full verification trio one final time, sync `docs/architecture/lifecycle.md` if lifecycle-impacting files changed, and update `CLAUDE.md` § "Boot-to-browser status" with the new alpha tag.
- **Priority:** P3 (sign-off)
- **Independent test:** All three verification gates green; `scripts/check-lifecycle-drift.sh` exits 0; `migration-notes.md` is finalized; `CLAUDE.md` reflects the new alpha and migration date.
- **Risks:** Lifecycle drift check fails because controller/entity migrations changed lifecycle-impacting paths — required outcome is to update lifecycle.md, not bypass the check.
- **Dependencies:** WP06.
- **Estimated prompt size:** ~400 lines.
- **Prompt:** [tasks/WP07-final-verification-lifecycle-sync.md](./tasks/WP07-final-verification-lifecycle-sync.md)

**Tracking:**

- [ ] T025 Run final PHPUnit (zero failures, count ≥ baseline) (WP07)
- [ ] T026 Run final PHPStan (zero new findings) (WP07)
- [ ] T027 Run final smoke (200/200, latency < 2s) (WP07)
- [ ] T028 Run lifecycle drift check; update `lifecycle.md` if needed (WP07)
- [ ] T029 Finalize `migration-notes.md` (WP07)
- [ ] T030 Update `CLAUDE.md` § "Boot-to-browser status" (WP07)

---

## Dependency Graph

```
WP01 (baseline)
  └─> WP02 (composer bump)
        ├─> WP03 (entity migration)         ┐
        ├─> WP04 (Discovery + Auth)         ├─> WP06 (residual drift)
        └─> WP05 (Management + API)         ┘     └─> WP07 (final + lifecycle)
```

WP03, WP04, WP05 share the dependency on WP02 only. They touch disjoint file sets and may be executed in parallel if a multi-lane flow is desired. Each can be verified independently because the post-bump failure surface (captured in WP02 → `baseline.md`) categorizes failures by type, and each WP closes its own category.

## MVP Scope

**WP01 + WP02 + WP03 + WP04 + WP05** is the minimum viable upgrade — composer constraints bumped, entity and controller contracts adapted, tests green, smoke green. WP06 only fires if residual drift surfaces. WP07 polishes documentation and verifies lifecycle alignment. For a quick "is it done?" pass, expect WP01–WP05 to land the substantive work.

## Parallelization Highlights

- WP01 internal: T001 (PHPUnit) ‖ T002 (PHPStan) — different tooling, no shared state.
- WP03 internal: T009 (KnowledgeItem) ‖ T010 (WikiLintReport) — different files.
- WP04 internal: T015–T017 ‖ each other — different controller files.
- Across WPs: WP03 ‖ WP04 ‖ WP05 (disjoint file ownership).

## Branch Strategy

Trunk-based on `main` per charter. All planning and implementation occur on `main`. PRs optional per solo-maintainer policy. Lane-based execution worktrees (allocated by `finalize-tasks`) all branch from and merge to `main`.
