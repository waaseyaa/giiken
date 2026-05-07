# Tasks: Eliminate alpha.173 Implicit-Array Shim

**Mission:** `01KQZX6VXH99R3SEKWAEG87RKT` — `eliminate-implicit-array-shim-01KQZX6V`  
**Plan:** [plan.md](./plan.md) · **Spec:** [spec.md](./spec.md) · **Occurrence map:** [occurrence_map.yaml](./occurrence_map.yaml)  
**Merge target:** `main` (trunk-based; implementation already merged on `main`)  
**Date:** 2026-05-08 (housekeeping / documentation closure)

---

## Outcome

Implementation and verification were completed in Cursor on **2026-05-07** (commit **`030f9ec`** on `main`) after the Spec Kitty plan agent hit usage limits. This `tasks.md` records the work as **done** so the mission folder matches repo reality and bulk-edit gates have a filed `occurrence_map.yaml`.

---

## Subtask index (all complete)

| ID | Description | Done |
|----|-------------|------|
| T001 | Normalize local Composer path-repo (`composer.local.json` → `../waaseyaa/packages/*` when upstream worktree path was deleted) | [x] |
| T002 | Annotate all SSR controller entry methods: `#[MapRoute]` / `#[MapQuery]` + attribute imports (six controllers, nineteen methods per migration-notes inventory) | [x] |
| T003 | Add `#[ContentEntityType]` + `#[ContentEntityKeys]` on `Community`, `KnowledgeItem`, `WikiLintReport` | [x] |
| T004 | Remove `fieldDefinitions` constructor arg; call `parent::__construct` with three arguments only | [x] |
| T005 | Switch `EntitiesProvider` to `EntityType::fromClass()` for all three entity types | [x] |
| T006 | Update `docs/architecture/lifecycle.md` for dispatch + entity registration contracts | [x] |
| T007 | PHPUnit + PHPStan + Vitest + lifecycle drift script green | [x] |
| T008 | File `occurrence_map.yaml` (DIRECTIVE_035) validated against schema | [x] |

---

## Work packages (conceptual — no separate WP prompt files)

### WP01 — Composer / local dev precondition

- **Goal:** Restore working Composer resolution (broken path-repo to deleted lane worktree).
- **Status:** Done (local `composer.local.json` only; gitignored).

### WP02 — Controller explicit binding

- **Goal:** Eliminate implicit-array shim dependency for `array $params` / `array $query` on dispatch entry points.
- **Status:** Done in `030f9ec` (`HomeController`, `WebLoginController`, `WebLogoutController`, `DiscoveryController`, `ManagementController`, `QueryApiController`).

### WP03 — Entity attribute-first registration

- **Goal:** Remove legacy `fieldDefinitions` forwarding; register via `EntityType::fromClass()`.
- **Status:** Done in `030f9ec` (`KnowledgeItem`, `WikiLintReport`, `Community`, `EntitiesProvider`).

### WP04 — Verification + lifecycle

- **Goal:** Tests, static analysis, lifecycle doc sync.
- **Status:** Done (`030f9ec` + prior mission commits on `main`).

---

## Follow-ups (outside this mission folder)

- Bump `waaseyaa/*` to **`^0.1.0-alpha.174`** when the tag appears on Packagist (C-001 version half); `composer show waaseyaa/core --all` currently lists `v0.1.0-alpha.173` as latest tag.
- Optional: add per-WP markdown under `tasks/` if you resume full Spec Kitty implement-review automation for historical audit only.
