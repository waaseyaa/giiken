# Giiken domain model

This document is the **architecture-level** summary of Giiken’s persisted domain. Mission-scoped research and column-level detail may also live under `kitty-specs/giiken-domain-modeling-01KR2HKT/` while a domain-modeling mission is active; this file is the stable entry point linked from [lifecycle.md](./lifecycle.md).

## Aggregates

| Aggregate | Entity type ID | PHP class | Role |
| --- | --- | --- | --- |
| Community | `community` | `App\Entity\Community\Community` | Multi-tenant root: slug, locale, wiki configuration (`WikiSchema`), sovereignty profile |
| Knowledge item | `knowledge_item` | `App\Entity\KnowledgeItem\KnowledgeItem` | Governed content belonging to exactly one community (`community_id`); access tier and provenance |
| Wiki lint report | `wiki_lint_report` | `App\Wiki\WikiLintReport` | Per-community lint output (`findings` JSON) |

## Relationships

- **Community → knowledge items:** one-to-many via `knowledge_item.community_id`.
- **Community → wiki lint reports:** one-to-many via `wiki_lint_report.community_id`.

Identifiers on the wire (routes, roles) use the community **slug** for humans and opaque ids for storage; see `CommunityRepository` / discovery controllers for resolution.

## Cross-cutting concerns

- **RBAC:** `KnowledgeItemAccessPolicy` evaluates `AccessTier` against roles derived from account roles (`giiken.community.{id}.{role}` pattern). Changing tiers or roles requires coordinated policy + test updates.
- **Provenance:** Structured `KnowledgeItemSource` on each item; rationale in [knowledge-item-source.md](./knowledge-item-source.md).
- **Search:** `KnowledgeItem` is indexed for community-scoped discovery; factories live next to the entity.

## Storage

SQL migrations under `migrations/` define columns. Content entities also use Waaseyaa’s `_data` blob for non-column keys—see the mission `data-model.md` for a migration-by-migration column list when extending the schema.

## Related docs

- [Application lifecycle](./lifecycle.md) — where providers register these types
- [Knowledge item source](./knowledge-item-source.md) — provenance JSON and indexed mirror columns
