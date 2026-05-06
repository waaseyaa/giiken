# Mission Spec: Upgrade Waaseyaa to alpha.173

**Mission ID:** `01KQYY1NT1BW6F7QKZA02969PB`
**Slug:** `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Mission type:** `software-dev`
**Target branch:** `main`
**Created:** 2026-05-06

---

## 1. Background and Motivation

Giiken declares its dependency on the Waaseyaa framework via the constraint
`^0.1.0-alpha.145` across 38 `waaseyaa/*` packages, but the working tree is
actually compiled against the `dev-main` branch of the upstream monorepo
through a `composer.local.json` path-repo override that symlinks
`../waaseyaa/packages/*`. Upstream has progressed to **v0.1.0-alpha.173** —
28 alpha releases ahead of the declared floor.

The discrepancy means the published constraint no longer reflects what
contributors actually run, downstream consumers cannot reproduce the
maintainer's build, and any breaking changes shipped between alpha.145 and
alpha.173 are silently absorbed by the path symlink. A reproducible build
requires the constraint and the lockfile to converge on a real published
version.

Per the project's "no workarounds" doctrine, framework gaps surfaced during
the upgrade are fixed upstream in `waaseyaa/framework`, not patched into
Giiken.

## 2. User Scenarios and Testing

This mission's primary user is the project maintainer working in this
repository, plus any future contributor who clones the repo.

### Scenario A — Fresh clone reproducibility

A new contributor clones Giiken on a machine without the `../waaseyaa/`
sibling directory, runs `composer install`, and gets a deterministic build
that resolves Waaseyaa packages from the registry (or stable git refs) at
the pinned alpha.173 version. The boot-to-browser smoke path
(`migrate → seed → serve → curl`) returns 200 with the seeded
`test-community` page rendering an Inertia response.

### Scenario B — Maintainer's local symlink workflow continues to work

The maintainer keeps `../waaseyaa/packages/*` symlinked for upstream-fix
iteration via `composer.local.json`. After the upgrade, that path-repo
override remains functional and continues to take precedence locally; only
the published constraint and the committed lockfile reflect alpha.173.

### Scenario C — Test suite verification

After the upgrade, the full PHPUnit suite (currently 238 tests across Unit
and Integration suites) runs to completion with zero failures, zero errors,
and zero warnings. The Vitest frontend suite likewise runs green. PHPStan
level 8 analysis on `src/` and `tests/` produces no new findings beyond the
pre-upgrade baseline.

### Scenario D — Drift sweep across the 7 service providers

Each of the 7 service providers (Routes, Frontend, Authz, Entities, Query,
Ingestion, App) registers correctly against the upgraded framework, with
any signature, return-type, or capability changes adapted in-place. The
provider list in `composer.json` (`extra.waaseyaa.providers`) continues
to bootstrap without errors during framework discovery.

### Scenario E — HTTP surface continues to dispatch

All 18 routes registered by `RoutesProvider` continue to dispatch through
the upgraded `WaaseyaaRouter`, `RouteBuilder`, and middleware/responder
contracts. Authentication, CSRF, rate-limiting, and JSON-API shaping all
behave as before.

### Edge cases

- A waaseyaa package between alpha.145 and alpha.173 introduced a
  backwards-incompatible change to a contract Giiken implements. Mission
  must surface and adapt to it.
- A waaseyaa package added a new required configuration key. Mission must
  surface and provide a value (or fix upstream to make it optional).
- Composer cannot resolve `^0.1.0-alpha.173` from the registry (e.g.,
  Packagist hasn't ingested all 38 packages at that tag). Mission must
  document the resolution path (path repo, vcs repo, satis, etc.).
- Lockfile reproduces with `dev-main` rather than `0.1.0-alpha.173` even
  after the constraint bump — local path repo continues to dominate
  resolution. Mission must define the canonical resolution mode for CI vs.
  local dev.

## 3. Functional Requirements

| ID | Requirement | Status |
|----|------------|--------|
| FR-001 | All 38 `waaseyaa/*` constraints in `composer.json` (excluding `waaseyaa/northcloud`) shall be updated to `^0.1.0-alpha.173`. | Draft |
| FR-002 | `composer.lock` shall resolve to a deterministic, reproducible state matching the new constraints when path-repo overrides are absent. | Draft |
| FR-003 | The `waaseyaa/northcloud` constraint shall remain at `@dev` (out of scope for this mission). | Draft |
| FR-004 | The PHPUnit test suite shall pass with the same test count as the pre-upgrade baseline (238 tests at mission start), with zero failures and zero errors. | Draft |
| FR-005 | The Vitest frontend test suite shall pass with no regressions versus the pre-upgrade baseline. | Draft |
| FR-006 | PHPStan level 8 analysis on `src/` and `tests/` shall produce no new findings beyond the pre-upgrade baseline. | Draft |
| FR-007 | The boot-to-browser smoke path (`./vendor/bin/waaseyaa migrate`, `./vendor/bin/waaseyaa giiken:seed:test-community`, server start, `curl /` returns 200 Inertia, `curl /test-community` returns 200 Inertia with seeded items) shall succeed end-to-end after the upgrade. | Draft |
| FR-008 | Every service provider in `extra.waaseyaa.providers` (App, Authz, Entities, Frontend, Ingestion, Query, Routes) shall continue to register and bootstrap without errors. | Draft |
| FR-009 | All 18 routes in `RoutesProvider` shall continue to dispatch correctly through the upgraded router and middleware stack, with authentication, CSRF, and rate-limit behavior unchanged. | Draft |
| FR-010 | All entity types registered in `AppServiceProvider` (`community`, `knowledge_item`, `wiki_lint_report`) shall continue to load, persist, and query through `EntityTypeManager` against the upgraded entity contracts. | Draft |
| FR-011 | The 5-step compilation pipeline (`Transcribe → Classify → Structure → Link → Embed`) shall continue to execute end-to-end against the upgraded `waaseyaa/ai-pipeline` contracts. | Draft |
| FR-012 | The `KnowledgeItemAccessPolicy` access resolution (5 roles × 4 access tiers) shall continue to enforce the documented matrix without change. | Draft |
| FR-013 | All 5 ingestion handlers (`Csv`, `Document`, `Html`, `Markdown`, `Media`) shall continue to register against the `IngestionHandlerRegistry` via the upgraded `FileIngestionHandlerInterface`. | Draft |
| FR-014 | A migration notes document shall enumerate every breaking change found between alpha.145 and alpha.173 and the adaptation applied in Giiken (per DIRECTIVE_003 Decision Documentation). | Draft |

## 4. Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|----|------------|-----------|--------|
| NFR-001 | Composer install on a fresh clone (no path symlink, warm cache) shall complete without manual intervention. | One `composer install` invocation, no manual edits | Draft |
| NFR-002 | The PHPUnit suite execution time shall not regress materially. | ≤ 120% of pre-upgrade baseline wall-clock time | Draft |
| NFR-003 | The boot-to-browser smoke path shall return the home page within human-perceivable latency. | `curl /` returns 200 in under 2 seconds on local dev | Draft |
| NFR-004 | The migration notes document (per FR-014) shall list every adapted contract with file path and reason, sufficient for a future contributor to retrace decisions without re-running the upgrade. | One bullet per adapted contract, each linked to a concrete `src/` file | Draft |

## 5. Constraints

| ID | Constraint | Status |
|----|------------|--------|
| C-001 | The PHP runtime requirement shall remain `>=8.4`. No upgrade to a newer PHP minor is in scope. | Draft |
| C-002 | The license shall remain `GPL-2.0-or-later`. | Draft |
| C-003 | Frontend npm dependencies (Vue, Inertia, Tailwind, Vitest, TypeScript) shall not be upgraded as part of this mission. | Draft |
| C-004 | The `composer.local.json` path-repo override shall continue to function for the maintainer's local workflow; the upgrade shall not require its removal. | Draft |
| C-005 | Per the project's "no workarounds" doctrine: any framework gap or contract drift discovered during the upgrade shall be fixed upstream in `waaseyaa/framework`, not patched into Giiken with consumer-side wrappers, hooks, or helpers. | Draft |
| C-006 | The mission shall land on `main` per the trunk-based solo-maintainer workflow defined in the charter. PRs are optional. | Draft |
| C-007 | The mission shall not introduce new entity types, routes, controllers, or pipeline steps. Scope is strictly an upstream version bump plus the adaptations needed to keep current behavior green. | Draft |

## 6. Success Criteria

The mission is complete when all of the following are simultaneously true:

1. `composer.json` declares `^0.1.0-alpha.173` for all in-scope `waaseyaa/*` packages.
2. `composer.lock` is committed and reproduces deterministically.
3. PHPUnit reports the same number of tests as the pre-upgrade baseline (or higher), with zero failures and zero errors.
4. Vitest reports zero failures.
5. PHPStan level 8 produces no new findings.
6. The boot-to-browser smoke path succeeds: `migrate`, `seed`, `serve`, `curl /` returns 200 with an Inertia "Discover" payload, `curl /test-community` returns 200 with seeded items.
7. The migration notes document is committed and lists every adapted contract.
8. Any framework gap discovered during the upgrade is either fixed upstream in `waaseyaa/framework` or documented as a deferred upstream issue with a tracking link — never worked around in Giiken.

## 7. Assumptions

- Upstream `v0.1.0-alpha.173` is published and resolvable through the same path-repo route the maintainer already uses; if Packagist coverage is incomplete, the mission will document the canonical resolution mechanism.
- The 238-test PHPUnit baseline asserted in the project CLAUDE.md is current at mission start; the implement phase will re-verify before doing any upgrade work.
- Breaking changes between alpha.145 and alpha.173 are bounded enough to adapt in-place without restructuring Giiken's domain layer.
- The maintainer has write access to the upstream `waaseyaa/framework` repository for any upstream-fix work that surfaces during the upgrade.

## 8. Out of Scope

- Frontend npm dependency upgrades (Vue, Inertia, Tailwind, Vitest, TypeScript).
- `waaseyaa/northcloud@dev` re-pinning — stays floating per current configuration.
- New features, new routes, new entities, new controllers, new pipeline steps.
- Removal of the `composer.local.json` path-repo override — the maintainer's local workflow is preserved.
- PHP minor-version upgrades.
- Schema migrations beyond what the upgrade itself may require.
- Refactoring or restructuring Giiken's existing service-provider, controller, or pipeline architecture beyond what the upgrade demands.

## 9. Risks and Open Questions

- **Cascade-failing test suite.** If alpha.145 → alpha.173 includes a high-impact contract change (e.g., entity schema, router builder, access policy attribute), large numbers of tests may fail simultaneously. Mitigation: research phase enumerates the changelog before code work begins; bisect by package family (entity, foundation, routing) if needed.
- **Resolution mode ambiguity.** Path repo currently dominates over the published constraint. Mission must define the canonical resolution mode for CI and for fresh clones; a decision is needed during research.
- **Upstream breaking changes without changelogs.** If the waaseyaa monorepo's release notes are sparse, the upgrade may require diffing each package's `CHANGELOG.md` (or commits) between alpha.145 and alpha.173. Mitigation: research phase produces a per-package change inventory.
- **Hidden coupling through `waaseyaa/northcloud@dev`.** Northcloud floats; if other waaseyaa packages have versioned constraints on it, the alpha.173 upgrade could surface a transitive resolution conflict. Mitigation: review northcloud's `composer.json` against the new constraints during research.
- **Test count drift.** New tests may have been added upstream-driven; "238" is a pre-upgrade snapshot, not a forever floor. Mitigation: capture the baseline in the implement phase before changing constraints, then verify "≥ baseline" rather than "= 238".

## 10. References

- Project root: `/home/jones/dev/giiken`
- Upstream monorepo: `/home/jones/dev/waaseyaa` (currently on `main` at tag `v0.1.0-alpha.173`)
- Charter: `.kittify/charter/charter.md`
- Project doctrine: `CLAUDE.md` ("No workarounds anywhere", "Waaseyaa DX standard")
- Lifecycle drift script: `scripts/check-lifecycle-drift.sh`
- Boot-to-browser smoke contract: `CLAUDE.md` § "Boot-to-browser status"
