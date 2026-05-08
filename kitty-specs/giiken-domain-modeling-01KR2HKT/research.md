# Research — Giiken domain modeling

**Mission:** `giiken-domain-modeling-01KR2HKT`  
**Phase:** discovery (Spec Kitty `software-dev`)

## Context

Giiken is a Waaseyaa consumer: multi-tenant **communities** own **knowledge items**; **wiki lint reports** capture validation output per community. Runtime registration lives in `App\Provider\EntitiesProvider` and related providers; persistence follows `ContentEntityBase` + migrations under `migrations/`.

## Decisions (baseline for this mission)

1. **Aggregate roots** — Treat `Community` and `KnowledgeItem` as the primary domain aggregates. `WikiLintReport` is a derived/report aggregate scoped by `community_id`, not a user-authored knowledge surface.
2. **Tenant boundary** — `KnowledgeItem::getCommunityId()` (via `HasCommunity`) is the tenancy key for access, discovery, ingestion, and search indexing. All new domain types that carry community-scoped data should declare the same invariant unless explicitly global.
3. **Storage split** — First-class fields map to SQLite columns (see `migrations/20260409_121000_add_giiken_entity_field_columns.php`, `20260410_122000_add_giiken_array_field_columns.php`, `20260415_140000_add_knowledge_item_source_columns.php`); residual keys remain in `_data` per Waaseyaa content-entity layout (`20260409_120000_create_giiken_entity_tables.php`).
4. **Provenance** — `KnowledgeItemSource` (JSON `source` + mirrored index columns) is canonical for origin, rights, and attribution; see `docs/architecture/knowledge-item-source.md` (cited in migration docblock).
5. **Access model** — `AccessTier` + `CommunityRole` + `KnowledgeItemAccessPolicy` encode RBAC; domain changes that touch visibility must preserve policy tests in `tests/Unit/Access/`.
6. **Framework coupling** — Entities stay on `ContentEntityBase` with `#[ContentEntityType]` / `#[ContentEntityKeys]`; avoid introducing a parallel DDD layer that duplicates field storage — extend typed getters and value objects under `src/Entity/` instead.

## Evidence map

| Topic | Location |
| --- | --- |
| Lifecycle / provider split | `docs/architecture/lifecycle.md` |
| Entity classes | `src/Entity/Community/Community.php`, `src/Entity/KnowledgeItem/KnowledgeItem.php`, `src/Wiki/WikiLintReport.php` |
| Value objects | `src/Entity/Community/WikiSchema.php`, `SovereigntyProfile.php`, `src/Entity/KnowledgeItem/Source/*` |
| Schema evolution | `migrations/*.php` |

## Open questions (for specify / plan)

1. **Domain services vs entities** — Compilation (`CompilationPipeline`), ingestion, and QA are largely application-layer today. Which invariants (e.g. state transitions for compilation, valid `KnowledgeType` × `WikiSchema` combinations) should move into explicit domain services or entity methods?
2. **Identifiers** — `community_id` on items is stored as `VARCHAR(128)`; document whether UUID string is the only supported form and whether numeric ids appear in any path.
3. **Future entities** — If governance (e.g. explicit grants, audit events) needs first-class tables, define how they relate to `community` and whether they share the same role-string encoding as today.

## Risks

- **Schema drift** — Any new persisted field must have a migration column (or intentional `_data` only) or `EntityRepository` persistence will diverge.
- **Lint report shape** — `findings` JSON structure should stay versioned or backward-compatible for UI and jobs.
