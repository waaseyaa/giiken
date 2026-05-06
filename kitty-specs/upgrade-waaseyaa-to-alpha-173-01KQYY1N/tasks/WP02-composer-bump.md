---
work_package_id: WP02
title: Composer constraint bump
dependencies:
- WP01
requirement_refs:
- FR-001
- FR-002
- FR-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts were generated on main; completed changes must merge back into main.
subtasks:
- T005
- T006
- T007
- T008
phase: Phase 2 - Foundational
assignee: ''
agent: ''
history:
- timestamp: '2026-05-06T16:14:17Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: composer.json
execution_mode: code_change
owned_files:
- composer.json
- composer.lock
- kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md
tags: []
---

# Work Package Prompt: WP02 — Composer constraint bump

## Objective

Perform the **big-bang composer change**: rewrite all 38 in-scope `waaseyaa/*` constraints in `composer.json` from `^0.1.0-alpha.145` to `^0.1.0-alpha.173`, regenerate `composer.lock`, and capture the post-bump failure surface so subsequent WPs can target their categories deterministically.

## Branch Strategy

Trunk-based on `main`. WP01 must be complete (baseline captured) before this WP starts.

## Context

- The 38 in-scope packages are listed in `composer.json` under `require:`. **Exclude `waaseyaa/northcloud`** (constraint stays `@dev` per FR-003).
- Path repo (`composer.local.json`) keeps the symlink at `../waaseyaa/packages/*` authoritative locally. The lockfile will reflect `dev-main` plus the v0.1.0-alpha.173 commit ref — this is expected per planning decision P3.
- Research recorded in `research.md` predicts **two failure categories** post-bump: (a) entity-registration via `fieldDefinitions:` (alpha.162 dropped this parameter), (b) controller signature using legacy `array $params, array $query, ...` (alpha.162 dropped this; alpha.173 added a compatibility shim that fires deprecation notices). T007 captures the actual surface to confirm or amend.

## Subtasks

### T005 — Edit `composer.json`: rewrite 38 `waaseyaa/*` constraints

**Purpose:** Update the published constraint to reflect the runtime version.

**Steps:**

1. Open `composer.json`. The `require` block contains 39 `waaseyaa/*` entries (38 in-scope + 1 northcloud).
2. For every line matching `"waaseyaa/<X>": "^0.1.0-alpha.145"` (excluding `waaseyaa/northcloud`), rewrite the value to `"^0.1.0-alpha.173"`.
3. Leave `"waaseyaa/northcloud": "@dev"` unchanged.
4. Do not add or remove packages.
5. Do not modify `composer.local.json`.

**Files:**

- `composer.json` (edit, ~38 single-line changes)

**Validation:**

- `grep -c '"waaseyaa/.*": "\^0.1.0-alpha.173"' composer.json` returns 38.
- `grep '"waaseyaa/northcloud"' composer.json` shows `@dev`.
- `grep -c '"waaseyaa/.*": "\^0.1.0-alpha.145"' composer.json` returns 0.

### T006 — Run `composer update 'waaseyaa/*' --with-all-dependencies`

**Purpose:** Regenerate `composer.lock` to reflect the new constraints.

**Steps:**

1. From the project root:
   ```bash
   composer update 'waaseyaa/*' --with-all-dependencies
   ```
2. Capture the output. Expect "Lock file operations:" with 38 packages updated. Path-repo entries should resolve to `dev-main` with the v0.1.0-alpha.173 commit reference.
3. If composer fails with a resolution error, stop and report — this is a real signal (likely a transitive constraint conflict from `waaseyaa/northcloud@dev` against the new floor).

**Files:**

- `composer.lock` (regenerated)

**Validation:**

- Composer exits 0.
- `composer.lock` shows the upstream commit reference matching `git -C ../waaseyaa rev-parse HEAD`.

### T007 — Capture post-bump failure surface

**Purpose:** Categorize PHPUnit + PHPStan failures so WP03–WP05 each know what they're closing.

**Steps:**

1. Run PHPUnit and capture **only the failure summary**, not the full output (it may be large):
   ```bash
   ./vendor/bin/phpunit 2>&1 | tail -100
   ```
   Look for:
   - Total test count post-bump (should equal or exceed baseline).
   - Failure / error count.
   - First-line of each unique failure message — categorize as:
     - `entity-registration`: messages mentioning `fieldDefinitions:`, `EntityType`, `EntityTypeManager`, attribute-first errors.
     - `controller-signature`: messages mentioning `AppParameterBindingBuilder`, parameter binding, `array $params`.
     - `other`: anything else.
2. Run PHPStan and capture findings:
   ```bash
   ./vendor/bin/phpstan analyse src tests --level=8 --no-progress 2>&1 | tail -100
   ```
3. Append a "Post-bump failure surface" section to `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md` with:
   - Captured timestamp.
   - PHPUnit: total tests, failures, errors, count by category.
   - PHPStan: total findings, distinct error classes.
   - Note any deviation from research.md predictions (e.g., a category that wasn't predicted).

**Files:**

- `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md` (append)

**Validation:**

- `baseline.md` now contains both pre-upgrade and post-bump sections.
- Failure categories sum to total failures (no uncategorized).

### T008 — Commit `composer.json`, `composer.lock`, baseline update

**Purpose:** Persist the bump as a single revertible commit.

**Steps:**

1. Stage the three files:
   ```bash
   git add composer.json composer.lock \
           kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md
   ```
2. Commit with conventional-commit message:
   ```bash
   git commit -m "chore(deps): bump waaseyaa/* to ^0.1.0-alpha.173 (WP02)"
   ```
3. Verify the commit landed on `main`.

**Validation:**

- `git log -1` shows the commit.
- `git diff HEAD~1 composer.json` shows 38 line changes.

## Definition of Done

- [ ] `composer.json` declares `^0.1.0-alpha.173` for all 38 in-scope packages.
- [ ] `composer.lock` regenerated and committed.
- [ ] `waaseyaa/northcloud` constraint unchanged at `@dev`.
- [ ] `baseline.md` includes post-bump failure surface with category breakdown.
- [ ] One commit on `main`.
- [ ] Failure categories observed match `research.md` predictions, OR deviation is documented in `baseline.md`.

## Risks

- **Resolution failure.** If composer cannot resolve, the most likely cause is a transitive constraint from `waaseyaa/northcloud@dev` against a now-tighter sibling. If this fires, capture the resolver output verbatim in `baseline.md` and stop the WP — escalate to a planning revision.
- **Path repo silently drops the new constraint.** `composer.local.json` may make the registry constraint cosmetic. Verify by examining `composer.lock` for the v0.1.0-alpha.173 commit ref. If the lockfile shows alpha.145's commit, the upstream symlink isn't on the right tag — `git -C ../waaseyaa checkout v0.1.0-alpha.173` first.
- **Test count drops.** Should not happen — alpha tags rarely remove tests from the framework, and this WP doesn't touch Giiken's tests. If it does, investigate before proceeding.

## Reviewer Guidance

- Verify the diff on `composer.json` is exactly 38 lines, all `^0.1.0-alpha.145 → ^0.1.0-alpha.173`.
- Confirm `composer.lock` shows the upstream commit ref matching `git -C ../waaseyaa rev-parse v0.1.0-alpha.173`.
- The commit is intentionally **expected to break tests**. That's not a failure — it's the diagnostic input for WP03–WP05.

## Implementation Command

```bash
spec-kitty agent action implement WP02 --agent <agent-name>
```

(Depends on WP01.)
