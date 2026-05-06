# Implementation Plan: Upgrade Waaseyaa to alpha.173

**Mission ID:** `01KQYY1NT1BW6F7QKZA02969PB`
**Slug:** `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Mission type:** `software-dev`
**Target branch:** `main`
**Date:** 2026-05-06
**Spec:** [spec.md](./spec.md)

---

## Summary

Migrate Giiken from `waaseyaa/* ^0.1.0-alpha.145` to `^0.1.0-alpha.173` (28 alpha releases) using a big-bang composer change followed by targeted code adaptation. The two material breaking-change classes between these tags — attribute-first entity definition (alpha.162) and the dropped legacy controller signature (alpha.162, partially shimmed in alpha.173) — affect concrete, countable surfaces in Giiken: 2-3 entity registrations and 19 controller methods. Tests, PHPStan, and the boot-to-browser smoke path verify success.

## Technical Context

| Slot | Value |
|---|---|
| **Language/Version** | PHP `>=8.4` |
| **Primary dependencies** | 38 `waaseyaa/*` packages (currently `^0.1.0-alpha.145`, target `^0.1.0-alpha.173`); `nesbot/carbon ^3.0`; `symfony/dotenv ^7.4`; `symfony/yaml ^7.4` |
| **Storage** | SQLite via the framework's entity-storage substrate; migrations in `migrations/` |
| **Testing** | PHPUnit 10.5+ (Unit + Integration), Vitest 3 (frontend), PHPStan level 8 |
| **Target platform** | Linux server; PHP-FPM under Caddy; PHP built-in server for local dev |
| **Project type** | Single project — web application (Inertia.js Vue 3 SPA over Waaseyaa SSR) |
| **Performance goals** | Boot-to-browser smoke: `curl /` returns 200 in under 2 seconds (NFR-003) |
| **Constraints** | PHP 8.4+ runtime, GPL-2.0-or-later license, no consumer-side workarounds for framework gaps (project doctrine), `composer.local.json` path-repo override must remain functional |
| **Scale/scope** | 96 PHP source files; 38 package constraints; 7 service providers; 18 routes; 6 controllers; 19 controller methods touched; 3 entity types; 5 ingestion handlers; 5 pipeline steps |

### Confirmed planning decisions

| ID | Decision | Rationale |
|---|---|---|
| P1 | Big-bang: bump all 38 `waaseyaa/*` constraints in one composer change | The waaseyaa monorepo releases all packages together at one tag. Peer constraints between packages (e.g., `waaseyaa/access` requires `waaseyaa/entity ^0.1`) make incremental bumps fight each other. |
| P2 | Read upstream `CHANGELOG.md` between `v0.1.0-alpha.145..v0.1.0-alpha.173` once, build per-package inventory in `research.md`, then bump-and-test | Bump-and-see across 28 alphas × 38 packages produces too noisy a failure surface. A targeted reading of the changelog plus a focused grep over Giiken's source identifies the exact migration debt before the constraint bump. |
| P3 | Path repo (`composer.local.json`) is authoritative locally; `composer.lock` pinned to alpha.173 content; fresh-clone reproducibility deferred to a future mission | Maintainer's local workflow already depends on the symlink path repo for upstream-fix iteration. Forcing Packagist-only resolution this mission would block upstream-fix iteration. A separate mission can address fresh-clone reproducibility once the upgrade itself is stable. |

### Concrete migration debt (verified by grep over `src/`)

| Pattern | File count | Method count | Required action |
|---|---:|---:|---|
| Controllers using legacy `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` | 6 | 19 | Migrate to typed parameter injection per alpha.162; add `#[MapRoute]` / `#[MapQuery]` per alpha.173 shim guidance |
| Entity files passing `fieldDefinitions:` to `EntityType` constructor | 2 | — | Migrate to `EntityType::fromClass()` with `#[ContentEntityType]` + `#[Field]` attributes per alpha.162 |
| Service providers calling `setKernelResolver()` | 0 | — | None — already on the modern provider lifecycle |
| Controllers using `JsonResponseTrait::json()` / `jsonBody()` | 0 | — | None — `QueryApiController` uses its own local `jsonBody()` helper, not the framework trait |

The 19-method count is concentrated in: `DiscoveryController` (5), `ManagementController` (7), `QueryApiController` (3), `WebLoginController` (2), `WebLogoutController` (1), `HomeController` (1).

The 2 entity files with `fieldDefinitions:` are `KnowledgeItem.php` and `WikiLintReport.php`. `Community.php` does not match this pattern at the call sites probed — Phase 3 confirms whether it needs migration.

## Charter Check

**GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.**

Charter context (`spec-kitty charter context --action plan --json`) loaded in bootstrap mode. Action doctrine for `plan`:

- **DIRECTIVE_003 — Decision Documentation Requirement** ✅. Material technical decisions captured in this plan §"Confirmed planning decisions". Spec records risks; `research.md` records per-package change inventory.
- **DIRECTIVE_010 — Specification Fidelity Requirement** ✅. Plan §"Specification Traceability" maps every spec FR/NFR/C to a phase and verification step.
- **DIR-003 — Greenfield Removal Policy** (hoisted in alpha.162) ✅. Reinforces the project's "no workarounds" rule. This plan adopts that posture: when the framework removes a contract, Giiken migrates rather than preserving a parallel old surface.

**Project doctrine alignment:**

- **No workarounds anywhere** (project memory): Phase 5 explicitly handles upstream-fix actions for any gap surfaced during the upgrade. No consumer-side wrappers are permitted.
- **Don't present option menus** (project memory): Each phase prescribes a single approach.

No charter violations. **Charter Check: PASS.**

## Implementation Strategy

### Phasing overview

```
P0  Research                    → research.md (complete on plan finalization)
P1  Pre-upgrade baseline        → baseline.md (test count, PHPStan baseline, smoke success)
P2  Composer bump               → 38 constraints to ^0.1.0-alpha.173, lockfile regenerated
P3  Entity registration         → 2-3 entities migrated to attribute-first per alpha.162
P4  Controller migration        → 19 methods migrated to typed parameter injection per alpha.162/.173
P5  Residual contract drift     → upstream-fix any remaining gaps; document in migration-notes.md
P6  Final verification + drift  → tests + lint + smoke green; lifecycle.md synced; migration notes finalized
```

### Phase 0 — Research (complete)

Inputs: upstream `CHANGELOG.md` between `v0.1.0-alpha.145..v0.1.0-alpha.173`; targeted grep of Giiken `src/` for legacy patterns. Output: `research.md`. Status: complete.

### Phase 1 — Pre-upgrade baseline capture

Captures the green-state reference before any change.

- Run `./vendor/bin/phpunit`; record exit code, test count, assertion count, wall time.
- Run `./vendor/bin/phpstan analyse src tests --level=8`; record finding count (expected: 0).
- Run boot-to-browser smoke: `./vendor/bin/waaseyaa migrate`, `./vendor/bin/waaseyaa giiken:seed:test-community`, start server, `curl -i http://127.0.0.1:8080/` (expect 200), `curl -i http://127.0.0.1:8080/test-community` (expect 200, Inertia "Discovery/Index").
- Commit baseline notes to `baseline.md` adjacent to this plan.
- **Done when:** `baseline.md` is committed and shows zero failures across all three gates.

### Phase 2 — Composer constraint bump

The big-bang change.

- Edit `composer.json`: rewrite all 38 `waaseyaa/*` constraints (excluding `waaseyaa/northcloud`) from `^0.1.0-alpha.145` to `^0.1.0-alpha.173` in a single commit.
- Run `composer update 'waaseyaa/*' --with-all-dependencies` to regenerate `composer.lock`.
- Verify lockfile reflects path-repo resolution against the upstream monorepo at `v0.1.0-alpha.173` (entries should still show `dev-main` plus the v0.1.0-alpha.173 commit reference).
- Capture the failure surface: run PHPUnit and PHPStan once and append the failure categories (entity registration vs. controller signature vs. other) to `baseline.md`.
- **Done when:** `composer.json` and `composer.lock` are committed; `baseline.md` records the post-bump failure surface; categories observed match research.md predictions (or any divergence is documented).

### Phase 3 — Entity registration migration (alpha.162)

For each entity file passing `fieldDefinitions:` to `EntityType`:

- Add `#[ContentEntityType('id', label: '...', description: '...')]` attribute on the entity class.
- Add `#[Field(...)]` attributes to typed properties (or rely on `FieldTypeInferrer` for canonical PHP types).
- Replace `new EntityType(..., fieldDefinitions: [...])` call sites with `EntityType::fromClass(MyEntity::class)`.
- For test fixtures needing raw `EntityType` shapes, switch to `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub()`.

Run PHPUnit incrementally as each entity migrates; entity-registration-class failures should drop to zero.

- **Done when:** No source file in `src/` matches `fieldDefinitions:`; PHPUnit reports zero entity-registration-class failures.

### Phase 4 — Controller signature migration (alpha.162 + alpha.173)

For each of the 19 controller methods using the legacy `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` signature:

- Replace with typed parameter injection: route params become typed scalars/enums or `#[MapRoute]`-bound, query params become `#[MapQuery]`-bound, services injected directly.
- Where the controller currently reads `$params['communitySlug']`, migrate to `string $communitySlug` parameter (the route pattern `/{communitySlug}/...` provides it).
- Where the controller reads `$query['q']`, migrate to `#[MapQuery] ?string $q = null` or a typed query DTO.

The alpha.173 compatibility shim means tests likely pass mid-phase with deprecation notices; the phase is not "done" until no shim notices fire.

- **Done when:** No method in `src/Http/Controller/` uses unannotated `array $params` or `array $query`; PHPUnit + smoke remain green; deprecation log capture shows zero `implicit_array_unbound` and zero shim entries.

### Phase 5 — Residual contract drift cleanup

For any failure not predicted in `research.md`:

- **Fix it upstream in `waaseyaa/framework`** per project doctrine. Push a tagged release to the upstream monorepo, bump the alpha tag in Giiken's `composer.json` if a new tag is cut, re-test.
- Document each upstream-fix action in `migration-notes.md` with: framework PR/commit ref, symptom, resolution.
- If a fix cannot be made upstream in this mission's window, file a tracking issue in `waaseyaa/framework` and document the deferral in `migration-notes.md`. Never patch around it in Giiken.

- **Done when:** PHPUnit, PHPStan, and smoke are all green; `migration-notes.md` enumerates every upstream-fix or deferred-issue action.

### Phase 6 — Final verification and lifecycle drift sync

- Re-run the full verification trio: PHPUnit (zero failures, count ≥ baseline), PHPStan level 8 (zero new findings), smoke path (`migrate`, `seed`, `serve`, `curl /` 200, `curl /test-community` 200 with seeded items).
- Run `scripts/check-lifecycle-drift.sh`. If lifecycle-impacting files changed (HTTP controllers, providers, entities, pipeline), update `docs/architecture/lifecycle.md` in the same commit.
- Finalize `migration-notes.md`: per-adapted-contract bullet with `src/` file path and reason (per FR-014, NFR-004).
- Update `CLAUDE.md` § "Boot-to-browser status" to reflect the new alpha tag and migration date.
- **Done when:** all three verification gates green, lifecycle drift check passes, `migration-notes.md` committed.

## Project Structure

### Documentation (this feature)

```
kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/
├── spec.md              # Mission spec (created by /spec-kitty.specify)
├── meta.json            # Mission metadata (created by /spec-kitty.specify)
├── plan.md              # This file (created by /spec-kitty.plan)
├── research.md          # Phase 0 output: per-package change inventory
├── data-model.md        # Phase 1 output: minimal — see notes within
├── quickstart.md        # Phase 1 output: verification runbook
├── contracts/           # Phase 1 output: minimal — see notes within
├── checklists/
│   └── requirements.md  # Spec quality checklist (created by /spec-kitty.specify)
├── tasks/               # Created by /spec-kitty.tasks (NOT this command)
├── baseline.md          # Phase 1 output (created during execution, not by /plan)
└── migration-notes.md   # Phase 5–6 output (created during execution, not by /plan)
```

### Source code (repository root)

```
giiken/
├── src/                                # 96 PHP files, App\ namespace (PSR-4)
│   ├── Access/                         # 4 files — KnowledgeItemAccessPolicy etc.
│   ├── Console/                        # 3 files — CLI commands
│   ├── Entity/
│   │   ├── Community/                  # ← Phase 3: confirm no fieldDefinitions: usage
│   │   ├── KnowledgeItem/              # ← Phase 3: KnowledgeItem.php migration
│   │   └── ... (Source/, HasCommunity)
│   ├── Export/                         # 5 files — import/export services
│   ├── Http/
│   │   ├── Controller/                 # ← Phase 4: 6 files, 19 methods to migrate
│   │   ├── Inertia/                    # InertiaHttpResponder (no migration needed)
│   │   ├── RateLimit/                  # 3 files
│   │   └── Api/Ask/                    # request/validator
│   ├── Ingestion/                      # 17 files (handlers, converters, jobs)
│   ├── Pipeline/                       # 12 files (5 steps, 4 providers, payload)
│   ├── Provider/                       # 7 service providers (no migrations needed)
│   ├── Query/                          # 18 files (search, qa, reports)
│   └── Wiki/                           # ← Phase 3: WikiLintReport.php migration
├── tests/                              # 57 files, mirrors src/
├── migrations/                         # 5 entity-table migrations
├── resources/js/                       # frontend (out of scope)
├── composer.json                       # ← Phase 2: 38 constraints to bump
├── composer.local.json                 # path-repo override (untouched)
├── composer.lock                       # ← Phase 2: regenerate
├── docs/architecture/lifecycle.md      # ← Phase 6: drift sync
├── scripts/check-lifecycle-drift.sh    # ← Phase 6: gate
└── CLAUDE.md                           # ← Phase 6: § "Boot-to-browser status" update
```

**Structure decision:** Single project, web-application layout. No new directories or restructuring required. The migration touches `composer.json`, `composer.lock`, three entity files, six controller files, optionally `docs/architecture/lifecycle.md`, and produces two new mission artifacts (`baseline.md`, `migration-notes.md`) inside the kitty-specs feature directory.

## Phase 1 Outputs (data-model and contracts)

### data-model.md

This mission introduces no new entities. The three existing entities (`Community`, `KnowledgeItem`, `WikiLintReport`) keep their fields, relationships, and access semantics. What changes is **how they are registered** with `EntityTypeManager`: from constructor-time `fieldDefinitions:` to compile-time `#[ContentEntityType]` + `#[Field]` attributes consumed via `EntityType::fromClass()`. The data-model.md file documents this no-domain-change contract explicitly.

### contracts/

This mission introduces no new HTTP contracts. The 18 existing routes registered in `RoutesProvider` keep their methods, paths, requirements, authentication policies, and request/response shapes. What changes is **how the controller methods receive parameters**: from generic `array $params, array $query` to typed parameter injection. The contracts/ directory documents that no schema or path is altered, and lists the 19 method signatures whose internal parameter binding changes.

## Risks and Mitigations

| Risk | Likelihood | Mitigation |
|---|---|---|
| Cascade-failing tests after composer bump | High | Phase 2 explicitly captures the failure surface; Phases 3–4 work through it incrementally |
| Undocumented contract change between alpha.145 and alpha.173 | Medium | Phase 5 dedicated to residual drift; per-package research.md inventory reduces blind spots |
| Path-repo resolution masks a registry-only failure mode | Medium | Mission scope explicitly defers fresh-clone reproducibility (P3) |
| `waaseyaa/northcloud@dev` floats and silently catches a transitive constraint conflict | Low | Phase 2 captures the lockfile diff |
| Test count baseline (238) is wrong at mission start | Low | Phase 1 re-captures the baseline; verify "≥ baseline" rather than "= 238" |
| Lifecycle drift check fails because controllers were migrated | Medium | Phase 6 includes the lifecycle.md update as a required step |

## Specification Traceability

| Spec ID | Implementation surface |
|---|---|
| FR-001 (38 constraints to alpha.173) | Phase 2 |
| FR-002 (lockfile reproducibility) | Phase 2 |
| FR-003 (northcloud stays @dev) | Phase 2 |
| FR-004, FR-005, FR-006 (test/lint baselines) | Phase 1 capture, Phase 6 verify |
| FR-007 (smoke path) | Phase 1, Phase 6 |
| FR-008 (provider bootstrap) | Phase 6 (verified by smoke) |
| FR-009 (route dispatch) | Phase 4, Phase 6 |
| FR-010 (entity registration) | Phase 3 |
| FR-011 (compilation pipeline) | Phase 6 (existing Pipeline tests) |
| FR-012 (access policy matrix) | Phase 6 (existing Access tests) |
| FR-013 (ingestion handlers) | Phase 6 (existing Ingestion tests) |
| FR-014 (migration notes) | Phase 5, Phase 6 |
| NFR-001..NFR-004 | Phase 1 capture, Phase 6 verify |
| C-001..C-007 | Constraints — enforced throughout |

## Complexity Tracking

No charter violations identified. No complexity-tracking entries required.

## Branch Contract (re-stated)

- Current branch at plan finalization: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: true
- Strategy: trunk-based on `main` per charter; PRs optional per solo-maintainer policy.

## Next Step

`/spec-kitty.tasks` — break this plan into work packages. Expect WPs that mirror the 6 execution phases, with the entity migration (P3) and controller migration (P4) likely each being multi-WP if granularity helps lane-based execution.
