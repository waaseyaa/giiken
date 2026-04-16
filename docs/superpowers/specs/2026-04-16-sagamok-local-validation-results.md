# Sagamok local compilation validation results

Date: 2026-04-16
Environment: local (`/home/jones/dev/giiken`, SQLite `storage/waaseyaa.sqlite`)

## Scope executed

- Community baseline renamed and validated:
  - `community.id = 1`
  - `slug = sagamok-anishnawbek`
  - `name = Sagamok Anishnawbek`
- NorthCloud sync run and persisted into Sagamok community.
- Four official/community starter briefs ingested through `giiken:ingest:file`.
- Source/type/tier posture validated with SQL checks.
- Discovery/search/ask routes smoke-tested locally.

## Command checkpoints

```bash
./bin/giiken northcloud:sync --limit=20
./bin/giiken giiken:ingest:file sagamok-anishnawbek storage/ingest/sagamok/governance-and-leadership.md
./bin/giiken giiken:ingest:file sagamok-anishnawbek storage/ingest/sagamok/land-and-territory.md
./bin/giiken giiken:ingest:file sagamok-anishnawbek storage/ingest/sagamok/programs-and-services.md
./bin/giiken giiken:ingest:file sagamok-anishnawbek storage/ingest/sagamok/language-and-culture.md
```

## Current dataset snapshot (community_id=1)

- Source origins:
  - `northcloud`: 20
  - `upload`: 4
  - `manual`: 5 (pre-existing seeded/manual records)
- Knowledge types + access tiers:
  - `cultural/public`: 24
  - `governance/public`: 2
  - `land/public`: 2
  - `relationship/public`: 1

## Local route checks

- `GET /sagamok-anishnawbek` → success (contains Sagamok + Discover payload)
- `GET /sagamok-anishnawbek/search` → success
- `GET /sagamok-anishnawbek/ask` → success

## Observed issue + remediation applied

- Issue: `giiken:ingest:file` failed at `StructureStep` when LLM output was malformed JSON.
- Fix applied:
  - Added graceful fallback in `StructureStep` to derive title/summary from markdown when JSON parsing fails.
  - Added explicit upload provenance writes in `EmbedStep` (`source_origin_type = upload`) so file-ingested records are distinguishable from manual and NorthCloud records.
- Result: all four Sagamok starter briefs now ingest successfully with `source_origin_type=upload`.

## Next follow-up candidates

1. Tighten NorthCloud scoping for Sagamok so NC imports are less generic and more community-specific.
2. Add frontmatter `source` URLs to each starter brief so `source_reference_url` is populated for upload-origin items.
3. Introduce a periodic curation pass that rebalances overrepresented `cultural` NC items into more precise categories where appropriate.

