# Data model — Giiken domain modeling

**Mission:** `giiken-domain-modeling-01KR2HKT`

## Entity summary

| Entity type ID | PHP class | Label key | Purpose |
| --- | --- | --- | --- |
| `community` | `App\Entity\Community\Community` | `name` | Multi-tenant container; wiki configuration and sovereignty metadata |
| `knowledge_item` | `App\Entity\KnowledgeItem\KnowledgeItem` | `title` | Primary governed content unit; search-indexed; compilation pipeline target |
| `wiki_lint_report` | `App\Wiki\WikiLintReport` | `title` | Per-community lint run output (`findings`) |

## Relationships

```text
Community (1) ──< KnowledgeItem     (community_id → community.uuid or id string per app usage)
Community (1) ──< WikiLintReport    (community_id)
```

- **Community → KnowledgeItem:** `knowledge_item.community_id` (required string, see migration).
- **Community → WikiLintReport:** `wiki_lint_report.community_id`.

## Community (`community`)

**Core layout (Waaseyaa content entity):** `id`, `uuid`, `bundle`, `name`, `langcode`, `_data` (`20260409_120000_create_giiken_entity_tables.php`).

**Giiken columns:** `slug` (unique index per `20260418_150000_add_unique_index_to_community_slug.php`), `wiki_schema`, `locale`, `created_at`, `updated_at`, `sovereignty_profile`, `contact_email` (`20260409_121000_add_giiken_entity_field_columns.php`).

**Value objects (in-memory / cast):**

- `WikiSchema` — default language, knowledge types, LLM instructions (stored in `wiki_schema` JSON).
- `SovereigntyProfile` — enum-backed string column.

## Knowledge item (`knowledge_item`)

**Core layout:** `id`, `uuid`, `bundle`, `title`, `langcode`, `_data`.

**Giiken columns:** `community_id`, `content`, `knowledge_type`, `access_tier`, `created_at`, `updated_at`, `compiled_at` (`20260409_121000_add_giiken_entity_field_columns.php`).

**List / JSON columns:** `allowed_roles`, `allowed_users`, `source_media_ids` (`20260410_122000_add_giiken_array_field_columns.php`).

**Provenance columns:** `source`, `source_origin_type`, `source_reference_url`, `source_ingested_at`, `rights_license` (`20260415_140000_add_knowledge_item_source_columns.php`).

**Enums:** `KnowledgeType`, `AccessTier`.

**Structured provenance:** `KnowledgeItemSource` (hydrated from `source` JSON); hot fields duplicated for indexing/query.

**Tenancy:** `getCommunityId()` returns the string stored in `community_id` (application treats this as the owning community’s stable id — align new code with existing seeds and repositories).

## Wiki lint report (`wiki_lint_report`)

**Core layout:** `id`, `uuid`, `bundle`, `title`, `langcode`, `_data`.

**Giiken columns:** `community_id`, `created_at`, `updated_at` (`20260409_121000_add_giiken_entity_field_columns.php`).

**Findings:** `findings` (`20260410_122000_add_giiken_array_field_columns.php`) — JSON array text of lint results for the community.

## Cross-cutting

- **Search:** `KnowledgeItem` implements `SearchIndexableInterface`; metadata/document factories live beside the entity.
- **RBAC:** Account roles use pattern `giiken.community.{communityId}.{roleSlug}`; see `KnowledgeItemAccessPolicy` and `CommunityRole` enum in codebase (not duplicated here — keep single source in PHP).

## Out of scope for discovery-only doc

Exact JSON schemas for `findings` and every `_data` key — to be pinned during specify/plan if this mission adds validation or codegen.
