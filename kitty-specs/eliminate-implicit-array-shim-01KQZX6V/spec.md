# Mission Spec: Eliminate the alpha.173 Implicit-Array Shim

**Mission ID**: `01KQZX6VXH99R3SEKWAEG87RKT` (mid8: `01KQZX6V`)
**Mission Slug**: `eliminate-implicit-array-shim-01KQZX6V`
**Mission Type**: `software-dev`
**Change Mode**: `bulk_edit`
**Target Branch**: `main`
**Created**: 2026-05-07  
**Status**: Complete (implementation on `main` commit `030f9ec`; bulk-edit `occurrence_map.yaml` + `tasks.md` filed 2026-05-08)

---

## Overview

Waaseyaa alpha.171/172 added a hard rejection for unannotated `array` parameters in controller methods. The historical canonical signature `function show(array $params, array $query, AccountInterface $account, Request $request)` stopped working. Alpha.173 introduced a name-keyed compatibility shim ("unannotated `array $params` defaults to `#[MapRoute]`, unannotated `array $query` defaults to `#[MapQuery]`") that restores the old behaviour, but every shim hit emits a structured `LoggerInterface::notice` so consumers can inventory their migration debt. The shim is explicitly framed as a migration runway, not a permanent feature — when Waaseyaa removes it (next breaking-change cycle), every Giiken controller method that still uses the historical signature breaks.

This mission migrates Giiken off the shim. The work is bounded and inventoried: file/method list lives in `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/migration-notes.md`, generated during the alpha.173 upgrade mission and explicitly deferred there as RISK-2.

**This mission is a bulk edit.** It changes the same identifier patterns (`array $params`, `array $query`, deprecated `fieldDefinitions:` form) across many files. Per the spec-kitty bulk-edit guardrail (DIRECTIVE_035), [`occurrence_map.yaml`](./occurrence_map.yaml) covering all 8 standard categories is filed and schema-validated (post-implementation housekeeping, 2026-05-08).

---

## What's being renamed

This is the explicit rename target the bulk-edit guardrail requires:

| From (current state) | To (target state) |
|---|---|
| `function ...(array $params, ...)` (unannotated) | `function ...(#[MapRoute] array $params, ...)` |
| `function ...(array $query, ...)` (unannotated) | `function ...(#[MapQuery] array $query, ...)` |
| Other unannotated `array $foo` parameters that bind to `[]` and emit `implicit_array_unbound` | Replace with explicitly-typed parameter (concrete attribute or other binding) — case-by-case |
| Entities using deprecated `fieldDefinitions:` form (per migration-notes.md inventory) | Current API shape (TBD per upgrade mission's notes — confirm during plan) |

Imports of `Waaseyaa\SSR\Http\AppController\MapRoute` and `MapQuery` are added where needed.

---

## User Scenarios & Testing

### Primary user story

**US-1: Application maintainer keeping Giiken green against framework upgrades**

As the maintainer of Giiken, I want every controller method to bind its `array` parameters via explicit attributes (`#[MapRoute]`, `#[MapQuery]`, or another concrete attribute) so that when Waaseyaa removes the alpha.173 compatibility shim, Giiken keeps working without an emergency response.

**Acceptance**: Running Giiken against any framework version that has removed the implicit-array shim (current alpha.174 and later) results in zero `implicit_array_default` or `implicit_array_unbound` notices fired from Giiken-side controller dispatch during a smoke that exercises every migrated controller method.

### Acceptance scenarios

| ID | Scenario | Expected outcome |
|---|---|---|
| AS-1 | Run `vendor/bin/phpunit` after migration | All existing tests pass; no test changes required to keep them green (test-side renames are limited to fixtures that name controller methods, not behavior changes). |
| AS-2 | Run `vendor/bin/phpstan analyse src/` after migration | Zero new errors; zero new entries in `phpstan-baseline.neon` unless explicitly justified in a per-WP commit body. |
| AS-3 | Run `npm run test:js` after migration | Frontend tests pass unchanged. |
| AS-4 | Boot Giiken against `waaseyaa/* ^0.1.0-alpha.174` and exercise every migrated controller method via routes (manual smoke or integration test) | Zero `implicit_array_default` notices, zero `implicit_array_unbound` notices in server logs from Giiken-side controller dispatch. |
| AS-5 | Read every controller signature inventoried in migration-notes.md | Each previously-unannotated `array $params` carries `#[MapRoute]`; each previously-unannotated `array $query` carries `#[MapQuery]`; each other previously-flagged `array` parameter has an explicit attribute or a different parameter type. |
| AS-6 | Read every entity flagged for `fieldDefinitions:` migration in migration-notes.md | Each uses the current API shape; no remaining references to the deprecated form. |
| AS-7 | `git status` after the mission completes | Clean tree (no leftover migration scaffolding, no `.bak` files, no commented-out old signatures). |

### Edge cases

- **Mixed-argument signatures**: A method that takes `array $params, AccountInterface $account, array $query` (account injected between the two arrays) must keep its parameter order and only annotate the `array` parameters.
- **Nullable/optional `array` parameters**: A signature like `array $params = []` must keep its default value alongside the new attribute: `#[MapRoute] array $params = []`.
- **Methods that take `array $params` but never read it**: Annotate anyway. Removing unused parameters is out of scope.
- **Methods that re-declared the parameter type post-shim** (e.g., `Waaseyaa\Foundation\Http\Request $request`): Already migrated. The migration-notes.md inventory excludes these.
- **Test methods with the same `array $params` signature**: If the test exercises a controller method via a fixture that mirrors the controller's signature, the test fixture should be updated alongside the controller.
- **Method bodies that introspect `$params` or `$query` shape** (e.g., `if (isset($params['id']))`): The introspection logic stays unchanged. Only the parameter declaration is rewritten.

---

## Requirements

### Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | Every controller method that currently has an unannotated `array $params` parameter MUST be migrated to carry the `#[MapRoute]` attribute on that parameter. | Proposed |
| FR-002 | Every controller method that currently has an unannotated `array $query` parameter MUST be migrated to carry the `#[MapQuery]` attribute on that parameter. | Proposed |
| FR-003 | Every other controller-method `array $X` parameter currently flagged by the framework's `implicit_array_unbound` notice MUST be migrated to have an explicit binding (a concrete attribute, a different parameter type, or removed if confirmed unused — case-by-case decision recorded in the per-WP commit body). | Proposed |
| FR-004 | Every entity flagged in `migration-notes.md` as using the deprecated `fieldDefinitions:` form MUST be migrated to the current API shape. | Proposed |
| FR-005 | The migration MUST add `use Waaseyaa\SSR\Attribute\MapRoute;` (canonical import) to any file that gains a `#[MapRoute]` reference. Same for `MapQuery` from `Waaseyaa\SSR\Attribute\MapQuery`. | Proposed |
| FR-006 | The migration MUST preserve every controller method's parameter order and default values. Only the attribute list before the type changes. | Proposed |
| FR-007 | The migration MUST NOT remove or modify the body of any controller method, except to repair imports introduced by FR-005. | Proposed |

### Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|---|---|---|---|
| NFR-001 | The migrated codebase MUST emit zero `implicit_array_default` or `implicit_array_unbound` notices during a representative smoke run that exercises every migrated controller method. | Zero notices captured in server logs across the smoke run; logs reviewed and attached to the mission's `artifacts/`. | Proposed |
| NFR-002 | Existing PHPUnit suites MUST keep passing without test-logic changes. | `vendor/bin/phpunit` exits 0; only updates to test files are mechanical fixture or signature-mirroring changes documented in the per-WP commit body. | Proposed |
| NFR-003 | Existing Vitest suites MUST keep passing unchanged. | `npm run test:js` exits 0. No frontend file changes expected as part of this mission. | Proposed |
| NFR-004 | Static analysis MUST stay clean. | `vendor/bin/phpstan analyse src/` reports zero new errors and adds zero new entries to `phpstan-baseline.neon`. | Proposed |
| NFR-005 | The mission MUST NOT introduce new composer dependencies. | `composer.json` `require` and `require-dev` blocks unchanged. | Proposed |

### Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | Giiken's pre-mission `composer.local.json` path-repos to a deleted worktree (cleaned up by the prior mission's merge). The first work package MUST normalize that state — bump the `waaseyaa/*` constraint to `^0.1.0-alpha.174` (the published version that includes the CSRF fix) OR remove the path-repo entry — before any controller migration begins. | Satisfied: path-repo repointed to `../waaseyaa/packages/*` (local). `^0.1.0-alpha.174` not on Packagist yet; constraints remain `^0.1.0-alpha.173`. |
| C-002 | Out of scope: any migration debt in `migration-notes.md` that is NOT related to the implicit-array shim or the deprecated `fieldDefinitions:` form. | Required |
| C-003 | Out of scope: refactoring of controller method bodies beyond the import additions required by FR-005. | Required |
| C-004 | Out of scope: composer constraint bumps beyond C-001 (no opportunistic upgrades). | Required |
| C-005 | All work merges into the `main` branch on `/home/jones/dev/giiken`. No work in `/home/jones/dev/waaseyaa` (the framework repo) is part of this mission. | Required |
| C-006 | Per the bulk-edit guardrail (DIRECTIVE_035), an `occurrence_map.yaml` covering all 8 standard categories MUST be produced during `/spec-kitty.plan` and validated before implementation begins. | Satisfied: [`occurrence_map.yaml`](./occurrence_map.yaml) filed 2026-05-08; validated via `specify_cli` schema (post-implementation). |
| C-007 | The framework's structured-notice channel (`Waaseyaa\Foundation\Log\...`, `LoggerInterface::notice`) MUST NOT be modified. The mission only consumes the notice channel as a verification signal (counting notices); it does not change the channel itself. | Required |

---

## Success Criteria

| ID | Outcome | How verified |
|---|---|---|
| SC-1 | Giiken survives a hypothetical Waaseyaa shim removal without source changes. | After this mission, simulate shim removal locally (e.g., point composer at a pre-shim alpha or a branch where the shim is removed) and observe Giiken keeps booting, all routes resolve, and integration tests pass. Document the simulation in the mission's `artifacts/`. |
| SC-2 | Notice count from a representative smoke is zero. | Tail server logs during the smoke; grep for `implicit_array_default` and `implicit_array_unbound` events keyed to Giiken-side `controller_class`. Zero matches required. |
| SC-3 | Inventory in `migration-notes.md` is fully exhausted. | Cross-reference each (controller_class, method, parameter_name) triple from migration-notes.md with the post-mission code; every triple is covered by an explicit attribute. The list of confirmed-unused triples (FR-003 case) is documented in the mission's `artifacts/migrations-resolved.md`. |
| SC-4 | All existing automated tests pass. | `composer test` (PHPUnit) and `npm run test:js` (Vitest) exit 0. |
| SC-5 | No new static-analysis debt. | `composer analyse` exits 0; `phpstan-baseline.neon` byte-identical to pre-mission unless a per-WP commit explicitly justifies any new entry. |

---

## Key Entities

This mission introduces no new domain entities. It changes the framework-attribute decoration on existing controller-method parameters and the API shape of a small set of entity field-definition declarations. State surfaces touched:

| Surface | Pre-mission shape | Post-mission shape |
|---|---|---|
| Controller method parameter declarations | `array $params` / `array $query` (unannotated) | `#[MapRoute] array $params` / `#[MapQuery] array $query` |
| Entity field-definition declarations (small set) | Deprecated `fieldDefinitions:` form (specific shape captured in migration-notes.md and confirmed during plan) | Current Waaseyaa entity API shape |
| Composer constraint for `waaseyaa/*` | `^0.1.0-alpha.173` (pinning a path-repo to a deleted worktree) | `^0.1.0-alpha.174` (or removal of path-repo) — first WP |

No database schema changes. No HTTP API changes. No JS frontend changes.

---

## Assumptions

1. The migration-notes.md inventory in the prior upgrade mission is current and exhaustive for the shim-affected surface. Plan phase will validate by re-running framework-side tracing on the current Giiken codebase.
2. The framework's `MapRoute` / `MapQuery` attribute classes are stable across alpha.174 and any near-future alpha. They are not on a deprecation path themselves.
3. Giiken's tests do not currently rely on the shim's `implicit_array_default` notice behaviour. (If they do, the test would need updating; this is treated as test-debt, not a new requirement.)
4. The deprecated `fieldDefinitions:` form has a known migration target in the framework documentation — the plan phase will confirm the canonical replacement shape by reading framework code or release notes.

---

## Dependencies

- **Pre-condition (resolved by C-001 in WP01)**: Giiken's composer state must be on `waaseyaa/* ^0.1.0-alpha.174` (the published version, with shim still active and notice tracing on) for the migration verification to be meaningful.
- **External**: Framework repository `waaseyaa/framework` at v0.1.0-alpha.174. No coordination needed — the framework already shipped.
- **Internal**: `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/migration-notes.md` (inventory of record). The plan phase reads this file in detail and produces a derived, mission-scoped inventory in `kitty-specs/eliminate-implicit-array-shim-01KQZX6V/research/inventory.md`.

---

## Out of Scope

- Other migration debt in `migration-notes.md` not related to the implicit-array shim or `fieldDefinitions:` form.
- Composer constraint bumps beyond what C-001 requires.
- Refactoring controller-method bodies, parameter ordering, or argument types beyond what FR-005/006/007 specify.
- Changes to Waaseyaa framework code (this mission is Giiken-side only).
- Changes to Giiken's frontend (Vue/TypeScript) code.
- Removal of the framework's shim itself (Waaseyaa's call; this mission only ensures Giiken survives that removal).

---

## Verification Plan

Three layers, gated:

1. **Static**: Read every controller file and entity file in scope; confirm via `grep` that no unannotated `array $params` / `array $query` remains and no `fieldDefinitions:` deprecated form remains.
2. **Test**: `composer test` (PHPUnit), `composer analyse` (PHPStan), `npm run test:js` (Vitest). All green.
3. **Smoke (verification gate)**: Boot Giiken against `waaseyaa/* ^0.1.0-alpha.174`; exercise every migrated controller via integration tests OR a manual route walkthrough; capture server logs; assert zero `implicit_array_default` / `implicit_array_unbound` notices fire from Giiken-side `controller_class` entries. Save the log capture into `kitty-specs/eliminate-implicit-array-shim-01KQZX6V/artifacts/`.

---

## Open Questions

None at end of specify. The deprecated `fieldDefinitions:` migration target shape is the only unknown — it will be resolved during `/spec-kitty.plan` by reading the framework documentation and existing migrated entities, not deferred.

---

## Branch Strategy

- Current branch at workflow start: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: **true**

(All values from `spec-kitty agent mission branch-context --json` at mission creation.)

---

## Bulk-edit notice

Per DIRECTIVE_035 and the spec-kitty bulk-edit classification skill, this mission's `meta.json` carries `change_mode: bulk_edit`. [`occurrence_map.yaml`](./occurrence_map.yaml) classifies all 8 standard categories (code_symbols, import_paths, filesystem_paths, serialized_keys, cli_commands, user_facing_strings, tests_fixtures, logs_telemetry) and was validated against the schema shipped in spec-kitty (`doctrine/schemas/occurrence-map.schema.yaml` via `specify_cli.bulk_edit.occurrence_map.validate_against_schema`). The map is the contract the implement-time and review-time gates enforce.
