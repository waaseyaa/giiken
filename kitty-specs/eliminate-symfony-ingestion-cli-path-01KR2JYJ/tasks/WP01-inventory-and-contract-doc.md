---
work_package_id: WP01
title: Inventory + contract doc
dependencies: []
requirement_refs:
- FR-001
planning_base_branch: main
merge_target_branch: main
phase: Phase 1 — Contract
assignee: ''
agent: ''
authoritative_surface: docs/specs/giiken-ingestion-cli-contract.md
owned_files:
- docs/specs/giiken-ingestion-cli-contract.md
- kitty-specs/eliminate-symfony-ingestion-cli-path-01KR2JYJ/tasks/WP01-inventory-and-contract-doc.md
execution_mode: code_change
---

# WP01 — Inventory + contract doc

**Mission:** `eliminate-symfony-ingestion-cli-path-01KR2JYJ`  
**Lane:** `lane-contract`

## Goal

Produce an authoritative inventory of Symfony (and Symfony-shaped) usage along **`giiken:ingest:file`** → registry → handlers → **`CompilationPipeline`** → persistence, and publish **`docs/specs/giiken-ingestion-cli-contract.md`**.

## Steps

1. Grep `Symfony\`, `InputInterface`, `OutputInterface`, array-callable patterns, and `CommandTester` under owned paths from [tasks.md](../tasks.md).
2. Read **`vendor/waaseyaa/foundation`** / **`vendor/waaseyaa/cli`** for alpha.174 **`HasCommandsInterface`** and command base types.
3. Draft the contract doc: canonical **`pipelineId`**, step order, MIME → handler matrix, exception mapping table, **allowlisted Symfony imports** (if any remain at CLI edge).
4. Update [spec.md](../spec.md) only if inventory contradicts it.

## Done when

- [ ] `docs/specs/giiken-ingestion-cli-contract.md` exists and matches repo reality post-grep.
- [ ] WP02/WP03 can implement against the doc without re-negotiating scope.
