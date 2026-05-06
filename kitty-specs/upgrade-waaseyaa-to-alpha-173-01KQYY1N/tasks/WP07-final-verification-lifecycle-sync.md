---
work_package_id: WP07
title: Final verification + lifecycle sync
dependencies:
- WP06
requirement_refs:
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T025
- T026
- T027
- T028
- T029
- T030
phase: Phase 6 - Polish (final verification)
assignee: ''
agent: ''
history:
- timestamp: '2026-05-06T16:14:17Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: docs/architecture/
execution_mode: code_change
owned_files:
- docs/architecture/lifecycle.md
- CLAUDE.md
tags: []
---

# Work Package Prompt: WP07 — Final verification + lifecycle sync

## Objective

Run the full verification trio one final time, sync `docs/architecture/lifecycle.md` if lifecycle-impacting files changed during the upgrade, and update `CLAUDE.md` § "Boot-to-browser status" with the new alpha tag and migration date. This WP is the mission's sign-off.

## Branch Strategy

Trunk-based on `main`. Depends on WP06.

## Context

- All migration work (WP02–WP05) and residual cleanup (WP06) should already be complete and merged. This WP is sign-off, not implementation.
- `scripts/check-lifecycle-drift.sh` is a CI gate per `CLAUDE.md` § "Lifecycle Documentation Governance". It compares lifecycle-impacting source files against the doc at `docs/architecture/lifecycle.md`. The upgrade likely touched HTTP controllers and entity registration paths, both of which are lifecycle-impacting; expect drift.
- `CLAUDE.md` § "Boot-to-browser status" currently records `^0.1.0-alpha.145` and a 2026-04-11 date. This WP updates both.

## Subtasks

### T025 — Run final PHPUnit (zero failures, count ≥ baseline)

**Purpose:** Confirm the test suite is green at mission end.

**Steps:**

1. From the project root:
   ```bash
   ./vendor/bin/phpunit
   ```
2. Compare output against `baseline.md`:
   - Exit code: 0
   - Test count: ≥ pre-upgrade baseline
   - Failure count: 0
   - Error count: 0
3. Capture the wall-time and compare against NFR-002 threshold (≤ 120% of pre-upgrade wall time). If regressed, document in `migration-notes.md` and surface to the maintainer.

**Validation:**

- Exit code 0; counts match Definition of Done.

### T026 — Run final PHPStan (zero new findings)

**Purpose:** Confirm static analysis is clean at mission end.

**Steps:**

1. From the project root:
   ```bash
   ./vendor/bin/phpstan analyse src tests --level=8 --no-progress
   ```
2. Compare against `baseline.md`:
   - Exit code: 0
   - Finding count: ≤ pre-upgrade baseline (zero new findings)

**Validation:**

- Exit code 0; finding count ≤ baseline.

### T027 — Run final boot-to-browser smoke (200/200, latency < 2s)

**Purpose:** Confirm the runtime path is healthy at mission end.

**Steps:**

1. Run the canonical smoke (per `quickstart.md`):
   ```bash
   ./vendor/bin/waaseyaa migrate
   ./vendor/bin/waaseyaa giiken:seed:test-community
   ./vendor/bin/waaseyaa serve > /tmp/giiken-final-serve.log 2>&1 &
   sleep 2
   time curl -i -s http://127.0.0.1:8080/ > /tmp/curl-home-final.txt
   time curl -i -s http://127.0.0.1:8080/test-community > /tmp/curl-tc-final.txt
   kill %1 || true
   ```
2. Verify:
   - Both curls return HTTP 200.
   - `/` payload identifies "Discover" Inertia page.
   - `/test-community` payload identifies "Discovery/Index" with 3 seeded items.
   - `/` latency under 2 seconds (NFR-003).
3. Inspect `/tmp/giiken-final-serve.log` for any framework deprecation notices or shim signals — expected: none.

**Validation:**

- 200/200 with seeded items.
- No deprecation notices in log.
- Latency under 2s.

### T028 — Run lifecycle drift check; update `lifecycle.md` if needed

**Purpose:** Keep `docs/architecture/lifecycle.md` synchronized with the migrated runtime.

**Steps:**

1. Run the drift check:
   ```bash
   ./scripts/check-lifecycle-drift.sh
   ```
2. If exit 0 with no drift detected, no doc update needed; record in `migration-notes.md`.
3. If drift detected, the script will identify which lifecycle-impacting files changed since the doc was last updated. Update `docs/architecture/lifecycle.md` to reflect:
   - The attribute-first entity registration pattern (replaces the legacy `fieldDefinitions:` description).
   - The typed-parameter-injection controller dispatch (replaces the legacy `array $params, array $query` description).
   - The new alpha tag (`v0.1.0-alpha.173`) as the documented framework version.
4. Re-run `./scripts/check-lifecycle-drift.sh` and confirm exit 0.

**Files:**

- `docs/architecture/lifecycle.md` (only if drift detected)

**Validation:**

- `./scripts/check-lifecycle-drift.sh` exits 0.
- If `lifecycle.md` was edited, the diff describes the migrated state.

### T029 — Finalize `migration-notes.md`

**Purpose:** Polish the migration notes per FR-014 / NFR-004.

**Steps:**

1. Open `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/migration-notes.md` (created in WP06).
2. Add or update sections:
   - **Final test/lint/smoke results** — record T025/T026/T027 outputs (test count, finding count, smoke latency).
   - **Final upstream tag adopted** — confirm the actual tag (likely `v0.1.0-alpha.173`, possibly higher if WP06 cut a release).
   - **Lifecycle drift outcome** — record whether `lifecycle.md` was updated.
3. Confirm every bullet has either a `src/` file path (for adapted contracts) or a PR/commit ref (for upstream fixes) or an issue link (for deferrals). No bare prose.

**Files:**

- `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/migration-notes.md`

**Validation:**

- File exists, all sections populated, every bullet has a real reference.

### T030 — Update `CLAUDE.md` § "Boot-to-browser status"

**Purpose:** Reflect the new framework version and migration completion in the project's primary doc.

**Steps:**

1. Open `/home/jones/dev/giiken/CLAUDE.md`.
2. In the "Boot-to-browser status" section, update:
   - The status date (currently `2026-04-11`) to today's date.
   - The framework version reference (currently `waaseyaa/* ^0.1.0-alpha.145`) to `^0.1.0-alpha.173` (or whichever tag landed).
   - The PHPUnit count line (currently "238/238 passing") to the post-upgrade actual count from T025.
   - Any Resolved (closed) sub-bullets if the upgrade closed an open framework issue tracked here.
3. Do **not** modify other sections of `CLAUDE.md` — scope is the boot-to-browser block.

**Files:**

- `CLAUDE.md` (boot-to-browser section only)

**Validation:**

- Diff shows only the boot-to-browser section changes.
- Date, alpha tag, and test count all reflect post-upgrade reality.

### T025–T030 wrap-up — Commit

After all six subtasks pass:

```bash
git add docs/architecture/lifecycle.md \
        CLAUDE.md \
        kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/migration-notes.md
git commit -m "docs(upgrade): finalize migration to waaseyaa ^0.1.0-alpha.173 (WP07)"
```

(Note: `migration-notes.md` was created in WP06 but its final touch-up happens here. This WP07 commit may include a small `migration-notes.md` diff alongside the lifecycle and CLAUDE.md updates.)

## Definition of Done

- [ ] Final PHPUnit: zero failures, test count ≥ baseline.
- [ ] Final PHPStan level 8: zero new findings.
- [ ] Final smoke: 200/200 with seeded items, latency under 2s, zero deprecation notices.
- [ ] `scripts/check-lifecycle-drift.sh` exits 0.
- [ ] `migration-notes.md` is finalized with per-adapted-contract bullets and final test/lint/smoke results.
- [ ] `CLAUDE.md` § "Boot-to-browser status" reflects post-upgrade reality.
- [ ] One commit on `main` covering the documentation updates.

## Risks

- **Lifecycle drift script flags many files.** Expected — controller signatures and entity registrations are both lifecycle-impacting. The right action is to update `lifecycle.md`, not bypass the check.
- **Test count regressed.** Should not happen if WP01–WP06 closed everything. If it did, return to WP06 to identify what's still broken — this WP shouldn't be the place to discover failures.
- **Smoke latency over 2s on local dev.** Could indicate a perf regression introduced by the framework upgrade. Investigate before signing off.

## Reviewer Guidance

- Confirm `migration-notes.md` is genuinely complete — every bullet has a concrete reference.
- Confirm `CLAUDE.md` boot-to-browser section now matches what's actually in `composer.json` (alpha tag) and what `phpunit` reports (test count).
- Confirm `lifecycle.md` (if edited) describes the new entity registration and controller signature patterns, not the old ones.
- Run `./scripts/check-lifecycle-drift.sh` yourself and confirm exit 0.

## Implementation Command

```bash
spec-kitty agent action implement WP07 --agent <agent-name>
```

(Depends on WP06.)
