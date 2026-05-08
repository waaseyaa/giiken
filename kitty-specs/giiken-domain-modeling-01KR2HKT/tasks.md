# Tasks: Giiken domain modeling

**Mission:** `01KR2HKT7J73P2TK4XP9J0D9S0` — `giiken-domain-modeling-01KR2HKT`  
**Plan:** [plan.md](./plan.md) · **Spec:** [spec.md](./spec.md) · **Research:** [research.md](./research.md)  
**Branch:** `main` (trunk-based planning)  
**Date:** 2026-05-08

---

## Subtask index

| ID | Description | WP | Parallel |
| --- | --- | --- | --- |
| T001 | Verify `data-model.md` against `migrations/*.php` and entity `casts`/getters | WP01 | |
| T002 | Verify `research.md` links and open questions still match codebase | WP01 | |
| T003 | Refresh CSV evidence rows for any doc edits | WP01 | |
| T004 | Add `docs/architecture/domain-model.md` (mirror + deep links) | WP02 | |
| T005 | Link new domain doc from `docs/architecture/lifecycle.md` §1.3 | WP02 | |
| T007 | Run `composer test` and `npm run test:js` on branch | WP02 | |

---

## Phase 1 — Kitty-spec canon + migration alignment

### WP01 — Domain docs and migration audit

- **Goal:** Satisfy FR-001, FR-002, FR-003 and NFR-001 by ensuring mission-local docs match migrations and key entity surfaces.
- **Priority:** P0
- **Independent test:** Reviewer can trace each table column named in `data-model.md` to a migration statement; `research.md` internal paths resolve.
- **Risks:** Late migration adds columns not reflected in kitty-specs.
- **Dependencies:** none
- **Prompt:** [tasks/WP01-domain-docs-and-migration-audit.md](./tasks/WP01-domain-docs-and-migration-audit.md)

**Tracking:**

- [x] T001 (WP01)
- [x] T002 (WP01)
- [x] T003 (WP01)

---

## Phase 2 — Architecture mirror + lifecycle link

### WP02 — Architecture documentation mirror

- **Goal:** Satisfy FR-004, FR-005, NFR-002, NFR-003 by publishing a durable `docs/architecture/domain-model.md`, linking from lifecycle, and proving green tests if any tracked file changes.
- **Priority:** P1
- **Independent test:** `docs/architecture/domain-model.md` exists; `lifecycle.md` references it; `./scripts/check-lifecycle-drift.sh` (or repo equivalent) passes if required files touched.
- **Risks:** Lifecycle drift gate fails if only kitty-spec changes — prefer additive lifecycle subsection pointing at new doc.
- **Dependencies:** WP01
- **Prompt:** [tasks/WP02-architecture-doc-mirror.md](./tasks/WP02-architecture-doc-mirror.md)

**Tracking:**

- [x] T004 (WP02)
- [x] T005 (WP02)
- [x] T007 (WP02)
