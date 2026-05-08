---
work_package_id: WP04
title: Tests + lifecycle
dependencies:
- WP02
- WP03
requirement_refs:
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
phase: Phase 3 — Verification
assignee: ''
agent: ''
authoritative_surface: tests/Unit/Console/IngestFileCommandTest.php
owned_files:
- tests/Unit/Console/IngestFileCommandTest.php
- tests/Unit/Architecture/IngestionCliPathNoSymfonyTest.php
- docs/architecture/lifecycle.md
execution_mode: code_change
---

# WP04 — Tests + lifecycle

**Mission:** `eliminate-symfony-ingestion-cli-path-01KR2JYJ`  
**Lane:** `lane-verify`

## Goal

Lock enforcement with tests and sync **`docs/architecture/lifecycle.md`** if operator/runtime flow text changes.

## Steps

1. Add **`tests/Unit/Architecture/IngestionCliPathNoSymfonyTest.php`** (name flexible) that:
   - Enumerates PHP files under WP02/WP03 scopes from [tasks.md](../tasks.md).
   - Asserts file contents contain **no** `use Symfony\` lines except those matching regexes from **`docs/specs/giiken-ingestion-cli-contract.md`** §Allowlist (parse as comment block or duplicate allowlist in test constant — **single source of truth** should be the doc; test can read the doc or import a shared PHP constant generated manually once).
2. Refactor **`tests/Unit/Console/IngestFileCommandTest.php`** to minimize Symfony surface per contract.
3. Run `./vendor/bin/phpunit`, `./vendor/bin/phpstan analyse src/`, `scripts/check-lifecycle-drift.sh` as needed.

## Done when

- [ ] New arch test passes on CI.
- [ ] Lifecycle doc updated if required by CLAUDE.md gate.
