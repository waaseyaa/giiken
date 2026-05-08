# Implementation Plan: Giiken domain modeling

**Branch**: `main` (planning on main; optional mission branch later)  
**Date**: 2026-05-08  
**Spec**: [spec.md](./spec.md)  
**Research**: [research.md](./research.md) · **Data model**: [data-model.md](./data-model.md)

## Summary

Canonize Giiken’s three-entity domain (Community, KnowledgeItem, WikiLintReport), tenancy via `community_id`, and provenance (`KnowledgeItemSource`) so future missions (ingestion, compilation, governance UI) extend from one **architecture-level** description. First increment: mission-local docs are authoritative; optional follow-up adds `docs/architecture/domain-model.md` and links from `lifecycle.md` without changing RBAC semantics.

## Technical context

**Language/Version**: PHP 8.4+ (Giiken), TypeScript/Vue for frontend (unchanged by doc-only WPs)  
**Primary Dependencies**: `waaseyaa/*` framework (entity, access, foundation, ssr)  
**Storage**: SQLite (dev/test); content entities with column + `_data` split  
**Testing**: PHPUnit + Vitest; access policy tests guard RBAC  
**Target Platform**: Linux / WSL2 dev; PHP built-in or FPM in prod  
**Project Type**: Waaseyaa consumer app (`src/`, `migrations/`, `docs/architecture/`)  
**Performance goals**: N/A for documentation phase  
**Constraints**: C-001–C-003 in spec (merge to Giiken `main`; no RBAC change without explicit WP)  
**Scale/scope**: Three entity types; bounded documentation + optional thin code/doc links

## Charter check

- No charter violations identified for documentation-first scope.
- Re-check if a WP introduces new persisted fields or routes (lifecycle drift script applies).

## Project structure

### Documentation (this feature)

```
kitty-specs/giiken-domain-modeling-01KR2HKT/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── checklists/requirements.md
├── research/evidence-log.csv
├── research/source-register.csv
└── tasks.md            # produced by `spec-kitty tasks`
```

### Source code (repository root)

```
src/Entity/Community/
src/Entity/KnowledgeItem/
src/Wiki/WikiLintReport.php
migrations/
docs/architecture/
tests/Unit/Access/
```

**Structure decision:** Domain types live under `src/Entity/**` and `src/Wiki/`; migrations remain canonical for columns; architecture narrative under `docs/architecture/`.

## Phased work (for tasks.md generation)

| Phase | Intent |
| --- | --- |
| P0 | Mission artifacts complete (discovery + specify) — **done in-session** |
| P1 | Optional: add `docs/architecture/domain-model.md` summarizing aggregates + link from `lifecycle.md` §1.3 |
| P2 | Optional: lightweight audit (script or checklist) comparing migration column set to documented fields |

## Complexity tracking

*None — no charter violations.*
