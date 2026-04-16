# Sagamok Anishnawbek compilation playbook (local Giiken)

## Scope

Operational guide for the local Sagamok slice in Giiken (`community_id = 1`, slug `sagamok-anishnawbek`).

## Source inventory (initial pass)

### NorthCloud

- Ingestion command: `./bin/giiken northcloud:sync --limit=20`
- Mapper: `App\Ingestion\NorthCloud\NcHitToKnowledgeItemMapper`
- Provenance expectation:
  - `source_origin_type = northcloud`
  - `source_reference_url` populated from NC hit URL

### Official/community starter briefs (upload path)

- `storage/ingest/sagamok/governance-and-leadership.md`
- `storage/ingest/sagamok/land-and-territory.md`
- `storage/ingest/sagamok/programs-and-services.md`
- `storage/ingest/sagamok/language-and-culture.md`

Ingest command pattern:

```bash
./bin/giiken giiken:ingest:file sagamok-anishnawbek storage/ingest/sagamok/<file>.md
```

Provenance expectation:

- `source_origin_type = upload`
- `source_reference_url` may be null unless frontmatter `source` is provided.

## Sagamok curation matrix (v1 local)

| Content class | `knowledge_type` | Default `access_tier` | Notes |
|---|---|---|---|
| Public governance references, leadership notices | `governance` | `public` | Keep publicly linked references public unless sensitivity changes. |
| Territory and land planning references | `land` | `public` | Promote to `staff`/`restricted` if drafts or internal planning docs are added. |
| Service/program directories and contacts | `relationship` | `public` | Use `members` if a record includes non-public contact details. |
| Language/cultural public notices/resources | `cultural` | `public` | Move to `members`/`restricted` if protocol-limited content appears. |
| Internal notes, meeting drafts, non-public records | any | `staff` or `restricted` | Must include clear authority rationale in source metadata / curation notes. |

## Practical SQL checks

```sql
-- Source split for Sagamok
SELECT source_origin_type, COUNT(*)
FROM knowledge_item
WHERE community_id='1'
GROUP BY source_origin_type;

-- Type/tier posture for Sagamok
SELECT knowledge_type, access_tier, COUNT(*)
FROM knowledge_item
WHERE community_id='1'
GROUP BY knowledge_type, access_tier
ORDER BY knowledge_type, access_tier;
```

