# KnowledgeItem Source — Structured Provenance

**Status:** implemented, v0.1.0-alpha
**Last updated:** 2026-04-15

## Why

A `source` field on a knowledge item is not just a dedup key. It is **provenance, attribution, licensing, and data-sovereignty evidence** rolled together. For an Indigenous knowledge platform that posture is a product requirement, not a nice-to-have:

- Every item needs a traceable origin (where did this come from, under what ingestion path?).
- Attribution must be preserved so creators and communities receive credit.
- Rights and consents must be enforceable by the pipeline, not just documented in prose.
- Community authority over knowledge (CARE principles) must be first-class.

A flat set of columns (`source_url`, `copyright_status`, `consent_public`, `consent_ai_training`) — the shape minoo currently uses — gets the basics but mixes concerns, pushes invariants into ad-hoc code, and loses upstream fidelity. This doc specifies the structured replacement.

## Shape

`knowledge_item.source` is a single JSON column holding a `KnowledgeItemSource` value object with four layered concerns:

```
KnowledgeItemSource
├── origin       SourceOrigin    — where & when it entered the system (required)
├── reference    SourceReference — the upstream work it describes (optional)
├── attribution  Attribution     — human credit (optional)
└── rights       Rights          — license, consents, TK Labels, CARE flags (required)
```

Four **hot fields** are mirrored to indexed columns so SQL queries stay fast:

| Column | Source path | Index |
|---|---|---|
| `source_origin_type` | `origin.type` | ✅ |
| `source_reference_url` | `reference.url` | ✅ |
| `source_ingested_at` | `origin.ingested_at` | ✅ |
| `rights_license` | `rights.license` | ✅ |

The hot-column mirror is maintained by `KnowledgeItem::setSource()` and by mappers that write the fields directly. Source-of-truth is always the JSON blob — columns are projections.

### Example JSON

```json
{
  "origin": {
    "type": "northcloud",
    "ingested_at": "2026-04-15T14:22:00+00:00",
    "system": "north-cloud-api-v1",
    "pipeline_version": "0.1.0"
  },
  "reference": {
    "url": "https://example.com/article",
    "source_name": "Example News",
    "external_id": "nc-abc123",
    "crawled_at": "2026-04-14T10:00:00+00:00",
    "quality_score": 82,
    "content_type": "article"
  },
  "attribution": {
    "creator": "Jane Doe",
    "publisher": "Example News",
    "published_at": "2026-04-13"
  },
  "rights": {
    "copyright_status": "external_link",
    "consent_public": true,
    "consent_ai_training": false,
    "tk_labels": ["TK Attribution", "TK Non-Commercial"],
    "care_flags": {
      "authority_to_control": "community-123"
    }
  }
}
```

## Industry-standard alignment

This shape is not invented — it composes several existing frameworks:

| Layer | Conventional model | What we take from it |
|---|---|---|
| **Origin** | W3C PROV-O (`Activity`, `wasGeneratedBy`) | The act of ingestion is distinct from the work |
| **Reference** | Schema.org `isBasedOn` / `CreativeWork`, Dublin Core `dc:source` | Upstream work identity |
| **Attribution** | Schema.org `creator`/`publisher`, Dublin Core `dc:creator`/`dc:publisher` | Human credit |
| **Rights (conventional)** | SPDX license identifiers, Creative Commons | License & copyright posture |
| **Rights (Indigenous)** | TK Labels (Local Contexts), CARE Principles (GIDA) | Community authority, protocol-driven access |

## Enums

Two values are typed as enums because the sets are small, stable, and drive application logic:

- **`OriginType`** — `northcloud`, `upload`, `manual`, `wiki`, `community`
- **`CopyrightStatus`** — `owned`, `licensed`, `fair_dealing`, `external_link`, `public_domain`

Other fields are strings or structured maps to leave room for evolution:

- **`rights.license`** — free-form, SPDX identifier where applicable (`CC-BY-4.0`, `CC-BY-NC-SA-4.0`, etc.).
- **`rights.tk_labels`** — array of Local Contexts label names (`TK Attribution`, `TK Non-Commercial`, `TK Community Voice`, etc.). Not enumerated because the set is managed externally and evolves.
- **`rights.care_flags`** — free-form map. Current conventions below, but intentionally flexible.

## CARE scope (v0.1.0-alpha)

The CARE Principles for Indigenous Data Governance — **C**ollective benefit, **A**uthority to control, **R**esponsibility, **E**thics — are modeled via `rights.care_flags`. In v0.1.0-alpha we commit to one convention and leave the rest open:

| Flag | Type | Meaning | Status |
|---|---|---|---|
| `authority_to_control` | community id (string) | Which community holds authority over this item. Ties directly into the existing RBAC model. | **Supported** |
| `collective_benefit` | bool | Whether use of this item is required to benefit the originating community. | Reserved — parse but don't enforce yet |
| `responsibility` | string | Reciprocal-use protocol (e.g., `reciprocal-use`, `consultation-required`). | Reserved |
| `ethics` | map | Ethical constraints (e.g., seasonal restrictions, gender-specific access). | Reserved |

**Enforcement today:** `authority_to_control` is the only CARE flag currently enforced by the access policy. It mirrors `community_id` in most cases and diverges when authority is transferred or shared.

**Enforcement later:** the other flags are parsed and persisted so they're not lost, but the pipeline does not yet act on them. Adding enforcement is a linting change, not a schema change.

## TK Labels scope (v0.1.0-alpha)

TK Labels (Traditional Knowledge Labels, curated by [Local Contexts](https://localcontexts.org/labels/traditional-knowledge-labels/)) express community-authorized usage rules. We store them as free-form strings — the pipeline does not currently enforce individual labels, but it does surface them and the access layer honors the common ones:

| Label | Surfaced in UI | Enforcement |
|---|---|---|
| `TK Attribution` | ✅ | Required attribution must render with the item |
| `TK Non-Commercial` | ✅ | Commercial export endpoints refuse the item |
| `TK Community Voice` | ✅ | Editing requires community-role escalation |
| Others (`TK Culturally Sensitive`, `TK Outreach`, etc.) | ✅ | Display only (v0.1.0) |

**Future work:** a `Wiki\Check\TkLabelEnforcementCheck` lint can surface items where the stored TK Labels conflict with their `access_tier` or `community_id`. Not scoped for v0.1.0-alpha.

## Hard gates the pipeline honors today

Two `rights` fields are hard gates, enforced immediately:

1. **`consent_public: false`** — item is never exposed to unauthenticated requests, regardless of `access_tier`.
2. **`consent_ai_training: false`** — item is excluded from `EmbedStep` and `LinkStep` of the compilation pipeline. LLM steps may still summarize on request but cannot produce vector embeddings or derive edges.

These are booleans on purpose. They are the minimum viable consent model and must be respected by every pipeline step. If a step disagrees, it is the step that is wrong.

## Multi-source (future work)

v0.1.0-alpha stores a **single** `source` per knowledge item. This is sufficient for the initial ingestion flows (NC pipeline, file upload, manual entry).

Multi-source is coming: one item can legitimately have multiple provenances — NC hit plus oral transcript plus community annotation plus cross-reference. The migration path:

1. Introduce `sources: []` on the entity (array of `KnowledgeItemSource`), deprecate the singular `source` field while preserving reads.
2. Keep the single `source` column as the "primary" source for index and default attribution.
3. Additional sources get appended; ordering reflects trust/primacy.
4. Lint checks flag conflicts (e.g., permissive rights from one source + restrictive from another → restrictive wins).

Not a v0.1.0-alpha concern. The value object shape is already compatible with being array-ified later.

## Migration (2026-04-15)

`migrations/20260415_140000_add_knowledge_item_source_columns.php`:

- Adds `source` TEXT + four indexed columns.
- Creates four indexes.
- Backfills every pre-existing row with `origin.type = "manual"` using the row's `created_at` as `ingested_at`.
- SQLite-safe `down()` is a no-op (SQLite cannot drop columns cleanly).

After this migration, every row has a valid `KnowledgeItemSource`. Code may assume the source is always present and never null.

## Ingestion integration

Ingestion paths that create knowledge items populate the full source:

| Path | `origin.type` | Notes |
|---|---|---|
| North Cloud sync (`waaseyaa/northcloud`) | `northcloud` | Full reference + attribution from NC hit |
| File upload (`giiken:ingest:file`) | `upload` | Reference URL is `file://...`, attribution from frontmatter when present |
| Manual admin entry | `manual` | Only origin required; attribution entered by curator |
| Wiki import (future) | `wiki` | Reference points at source wiki |
| Community contribution (future) | `community` | Attribution from authenticated user |

The NC mapper (`src/Ingestion/NorthCloud/NcHitToKnowledgeItemMapper.php`) is the reference implementation for how to produce a full source from an external ingestion path.

## Rationale: why a value object, not a flat table

A flat-column approach (`source_url`, `source_name`, `creator`, `publisher`, `license`, ...) would work but has three durable costs:

1. **Concerns collapse** — there's nowhere obvious for "this is about origin vs attribution" to live, so it doesn't.
2. **Schema ossifies** — every new source field is a migration, every app using the same entity disagrees.
3. **JSON fields hide but don't solve** — a single untyped JSON blob carries everything but gives us no invariants.

The value object gets both: typed structure where invariants matter (required fields, enum values, indexed projections) plus JSON elasticity where the world keeps changing (TK Labels, CARE conventions, new upstream systems). The four sub-objects map cleanly onto the four industry-standard concerns, so the mental model stays compact.

## Open questions

- Should `source_reference_url` be `UNIQUE`? Today it's only indexed — multiple items can legitimately reference the same upstream work (e.g., the same NC article manifesting as both `knowledge_item` and a future `event`). Multi-source will complicate this further. Keep non-unique for now; enforce uniqueness at the mapper level when desired.
- Where do per-community TK Label vocabularies live? v0.1.0 uses a global flat list. A `Community.wikiSchema.tkLabelVocabulary` extension is plausible.
- Does `care_flags.authority_to_control` ever diverge from `community_id`? If so, we need a model for shared/transferred authority. Flagged as future work.
