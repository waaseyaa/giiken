---
work_package_id: WP01
title: Pre-upgrade baseline capture
dependencies: []
requirement_refs:
- FR-004
- FR-005
- FR-006
- FR-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
- T004
phase: Phase 1 - Setup
assignee: ''
agent: "claude:opus-4-7:implementer:implementer"
shell_pid: "153580"
history:
- timestamp: '2026-05-06T16:14:17Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/
execution_mode: planning_artifact
owned_files:
- kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md
tags: []
---

# Work Package Prompt: WP01 — Pre-upgrade baseline capture

## Objective

Lock in the green-state reference values for **PHPUnit**, **PHPStan level 8**, and the **boot-to-browser smoke path** before any constraint changes. Without this, later phases cannot verify "≥ baseline" — they would only have anecdotes.

## Branch Strategy

Planning artifacts were generated on `main`; completed changes from this WP must merge back into `main` per the trunk-based, solo-maintainer policy in the project charter. Execution worktrees (if allocated by lane) branch from `main`. There are no PR gates required, but the maintainer may open one at their discretion.

## Context

- Mission: `upgrade-waaseyaa-to-alpha-173-01KQYY1N`. Spec → `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/spec.md`. Plan → `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/plan.md`. Quickstart runbook → `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/quickstart.md`.
- The project's `CLAUDE.md` claims the suite passes 238/238 — verify rather than trust.
- This WP produces no source-code change. It writes one new file: `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md`.
- Work in the project root (`/home/jones/dev/giiken`). Do not change the upstream `../waaseyaa` checkout.

## Subtasks

### T001 — Capture PHPUnit baseline [P]

**Purpose:** Record exact pre-upgrade test count, exit code, and wall time so later phases can compare against a real number.

**Steps:**

1. From the project root, run:
   ```bash
   ./vendor/bin/phpunit
   ```
2. Capture from the output:
   - Exit code (`echo $?` — must be `0` for the baseline to be valid).
   - The summary line, e.g., `Tests: 238, Assertions: 1234, ...`.
   - The wall-time line, e.g., `Time: 00:00.825`.
3. If exit code is non-zero, **stop the WP** and report the failure to the user — the upgrade cannot start from a red baseline. The maintainer must triage pre-existing failures before this mission can proceed.

**Validation:**

- Exit code is 0.
- Test count, assertion count, and wall time are recorded as verbatim strings (do not round).

### T002 — Capture PHPStan level 8 baseline [P]

**Purpose:** Record exact pre-upgrade static-analysis finding count.

**Steps:**

1. From the project root, run:
   ```bash
   ./vendor/bin/phpstan analyse src tests --level=8 --no-progress
   ```
2. Capture from the output:
   - Exit code (must be `0` to proceed).
   - The summary line — usually `[OK] No errors` for a clean baseline, or the finding count if any pre-existing findings remain.
3. If there are pre-existing findings, that is acceptable as a baseline (the upgrade will use "no new findings" rather than "zero findings"). Record the count exactly.

**Validation:**

- Exit code is 0 OR finding count is recorded as the explicit baseline number.

### T003 — Capture boot-to-browser smoke baseline

**Purpose:** Verify the documented boot path actually returns 200 with seeded items. Records latency for NFR-003 comparison.

**Steps:**

1. From the project root:
   ```bash
   ./vendor/bin/waaseyaa migrate
   ./vendor/bin/waaseyaa giiken:seed:test-community
   ```
   Capture the output of each command (expect "1 migration applied" / "community + 3 knowledge items" or similar).
2. Start the dev server in a backgroundable manner — either in a separate shell or via `&`:
   ```bash
   ./vendor/bin/waaseyaa serve &
   # or: php -S 127.0.0.1:8080 -t public public/index.php &
   ```
   Wait ~2 seconds for the server to bind.
3. Run the smoke curls and capture HTTP status + payload identification + wall time:
   ```bash
   time curl -i -s http://127.0.0.1:8080/ | head -20
   time curl -i -s http://127.0.0.1:8080/test-community | head -20
   ```
4. Verify:
   - `/` returns `HTTP/1.1 200 OK`, payload identifies the "Discover" Inertia page.
   - `/test-community` returns `HTTP/1.1 200 OK`, payload identifies "Discovery/Index" with seeded items.
   - Each request completes in well under 2 seconds (NFR-003 threshold).
5. Stop the server (`kill %1` or `Ctrl-C`).

**Validation:**

- Both curls return 200.
- Latency under 2 seconds for `/`.
- Server stops cleanly.

### T004 — Commit `baseline.md`

**Purpose:** Persist the captured numbers as a single authoritative reference for later phases.

**Steps:**

1. Create `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md` with the following structure:

   ```markdown
   # Pre-upgrade Baseline — Upgrade Waaseyaa to alpha.173

   **Captured:** <ISO 8601 timestamp>
   **Captured by:** WP01 (T001 + T002 + T003)
   **Project root:** /home/jones/dev/giiken
   **Upstream symlink target:** /home/jones/dev/waaseyaa @ <output of `git -C ../waaseyaa describe --tags`>

   ## PHPUnit

   - Exit code: 0
   - Tests: <NN>
   - Assertions: <MM>
   - Wall time: <HH:MM.NNN>

   ## PHPStan level 8 (`src/`, `tests/`)

   - Exit code: 0
   - Findings: <0 OR baseline count>

   ## Boot-to-browser smoke

   - `./vendor/bin/waaseyaa migrate`: <output line>
   - `./vendor/bin/waaseyaa giiken:seed:test-community`: <output line>
   - `curl -i http://127.0.0.1:8080/`: HTTP 200, identified as "Discover" Inertia page, latency <SS>s
   - `curl -i http://127.0.0.1:8080/test-community`: HTTP 200, identified as "Discovery/Index" with 3 seeded knowledge items, latency <SS>s

   ## Post-bump failure surface

   _To be appended in WP02 T007 after the composer bump._
   ```

2. Commit with a conventional-commit message:
   ```bash
   git add kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline.md
   git commit -m "chore(upgrade): capture pre-alpha-173 baseline (WP01)"
   ```

**Validation:**

- File exists at the canonical path.
- Commit lands on `main` and is visible in `git log`.

## Definition of Done

- [ ] All four subtasks T001–T004 marked complete.
- [ ] `baseline.md` is committed to `main` and contains real captured numbers (no placeholders).
- [ ] All three baseline gates (PHPUnit, PHPStan, smoke) recorded zero failures.
- [ ] No source-code files in `src/` were modified.

## Risks

- **Pre-existing red baseline.** If PHPUnit or PHPStan fails before any change, the upgrade cannot start. Stop the WP and surface the failures for triage. Do not "fix" them inside this WP — that's scope creep and would mask the upgrade's diagnostic value.
- **Dev server port conflict.** Port 8080 may be busy. Pick another (e.g., 8081) and adjust the curls accordingly.
- **Stale seeded data from prior runs.** If the SQLite database already has a `test-community`, the seed command may be idempotent or may complain. Either is acceptable; record what happens.

## Reviewer Guidance

- Check `baseline.md` shows real numbers, not template placeholders.
- Confirm `git -C ../waaseyaa describe --tags` was captured (this anchors the path-repo state).
- Confirm latency on `/` is well under the NFR-003 threshold.

## Implementation Command

```bash
spec-kitty agent action implement WP01 --agent <agent-name>
```

(No upstream dependencies — this is the first WP.)

## Activity Log

- 2026-05-06T16:42:39Z – claude:opus-4-7:implementer:implementer – shell_pid=153580 – Started implementation via action command
