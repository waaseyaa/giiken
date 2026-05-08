---
work_package_id: WP01
title: Domain docs and migration audit
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- NFR-001
- NFR-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T001
- T002
- T003
phase: Phase 1 - Kitty-spec canon
assignee: ''
agent: ''
history: []
authoritative_surface: kitty-specs/giiken-domain-modeling-01KR2HKT/
execution_mode: planning_artifact
owned_files:
- kitty-specs/giiken-domain-modeling-01KR2HKT/data-model.md
- kitty-specs/giiken-domain-modeling-01KR2HKT/research.md
- kitty-specs/giiken-domain-modeling-01KR2HKT/research/evidence-log.csv
- kitty-specs/giiken-domain-modeling-01KR2HKT/research/source-register.csv
- kitty-specs/giiken-domain-modeling-01KR2HKT/checklists/requirements.md
tags: []
---

# Work Package Prompt: WP01 — Domain docs and migration audit

## Objective

Align `data-model.md` and `research.md` with the **actual** SQLite migrations and entity classes so FR-001–FR-003 and NFR-001 are satisfied before any `docs/architecture/` mirror work.

## Scope

- Read `migrations/20260409_120000_create_giiken_entity_tables.php` through `20260418_150000_add_unique_index_to_community_slug.php`.
- Cross-check `Community`, `KnowledgeItem`, `WikiLintReport` persisted fields.
- Update mission CSV logs if claims change.

## Out of scope

- RBAC or access policy edits.
- New entity types.

## Verification

- Grep or manual table: every non-`_data` column you document exists in a migration.
- `research.md` paths exist.
