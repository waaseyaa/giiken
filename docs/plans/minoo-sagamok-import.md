# Minoo Sagamok Import Mapping

This document defines the extraction filters, normalization rules, and import behavior for transferring Minoo SQLite content into Giiken `knowledge_item` entities.

## Scope

- Source DB: `/home/jones/dev/minoo/storage/waaseyaa.sqlite`
- Export script: `/home/jones/dev/minoo/scripts/export_sagamok_knowledge.php`
- Import script: `/home/jones/dev/giiken/scripts/import_minoo_sagamok.php`
- Target community: `sagamok-anishnawbek`

## Extract Filters

- `event`: include rows where `_data.community_id` matches Sagamok community id OR title/_data contains `sagamok`.
- `group`: include rows where name/_data contains `sagamok`.
- `resource_person`: include rows where name/_data contains `sagamok`.
- `teaching`: include rows where title/_data contains `sagamok`.
- `dictionary_entry`: include all Ojibwe dictionary rows where `_data.language_code = "oj"` (optionally capped by `--dictionary-limit`).

## Normalization Rules

- Parse each `_data` JSON blob and preserve selected fields in curated `metadata`.
- Convert mixed timestamps (unix ints, unix strings, ISO text) to ISO-8601 UTC strings.
- Normalize booleans (`0/1`, `true/false`, strings) for consent flags.
- For dictionary definitions:
  - if array, join with `; `
  - if JSON-string array (for example `"[\"word\"]"`), decode then join
  - otherwise keep plain text
- Build normalized content text per source type:
  - events: description + location + time range
  - groups: description + website
  - resource people: bio + role IDs + offering IDs + website
  - teachings: description + content body
  - dictionary: word + definition + part of speech + stem + forms + source URL

## Curated Export Shape

Each curated row includes:

- `source`: `system`, `table`, `id`, `uuid`, stable `fingerprint`
- `community`: `name`, `slug`
- `title`, `content`
- `knowledge_type`, `access_tier`, `langcode`
- `source_url`
- `tags`
- `rights`: `consent_public`, `consent_ai_training`, `copyright_status`, `license`
- `timestamps`: `created_at`, `updated_at`
- `metadata`: source-type-specific details

## Giiken Import Mapping

- Every curated row maps to one `knowledge_item`.
- Knowledge type mapping:
  - `event` -> `event`
  - `group` -> `relationship`
  - `resource_person` -> `relationship`
  - `teaching` -> `cultural`
  - `dictionary_entry` -> `cultural`
- Access tier is set to `public`.
- Source provenance is written via `KnowledgeItem::setSource()` with:
  - `origin.type = wiki`
  - `origin.system = minoo-sqlite-export`
  - `reference.url = minoo://{table}/{id}` (stable dedupe key)
  - `reference.external_id = {table}:{id}`
  - `attribution.publisher = Minoo`
  - rights from curated consent/copyright fields

## Idempotency

- Upsert key: `knowledge_item.source_reference_url` equals `minoo://{table}/{id}`.
- On rerun:
  - one existing row -> update
  - no existing row -> create
  - multiple existing rows -> mark duplicate, skip, report

## Outputs

- Minoo export output directory:
  - `raw-*.json` snapshots
  - `curated-content.json`
  - `curated-dictionary.json`
  - `summary.json`
- Giiken import reports:
  - `storage/import-reports/minoo-sagamok-import-*.json`

