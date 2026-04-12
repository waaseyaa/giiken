# Phase 3: Query Layer — Design Spec

**Date:** 2026-04-05
**Issues:** #10, #11, #12, #13, #7 (plus #27 closure)
**Status:** Draft

---

## Overview

Phase 3 builds the query and retrieval layer on top of the ingestion pipeline completed in Phase 2. It delivers hybrid search, LLM-powered Q&A with citations, report generation, and full data export with round-trip import. An audio/video ingestion handler (#7, originally Phase 5) is included as a self-contained addition.

Completing #10 and #11 also closes #27 (MVP acceptance scenario).

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Work sequencing | Unit 1 (Search + Q&A) then Unit 2 (Reports + Export) | Q&A reuses search internals; reports and export share "consume items, produce documents" pattern |
| Search strategy | Hybrid scoring (0.6 semantic + 0.4 full-text) | Best result quality; both providers already exist independently |
| LLM provider routing | Defer to DI container | Q&A depends on `LlmProviderInterface` only; sovereignty-aware routing is a cross-cutting concern wired later |
| Report templating | PHP render classes (no Twig) | Three known report types with deterministic output; no new dependency needed |
| Import scope | Export + core import (no embedding re-import) | Embeddings regenerate via CompilationPipeline; proves round-trip without duplicating pipeline logic |

---

## Unit 1: Search + Q&A (#10, #11)

### Search Service

**Location:** `src/Query/SearchService.php`

Provides hybrid full-text + semantic search over KnowledgeItems, scoped to a community and filtered by the requesting account's access permissions.

#### Interface

```php
namespace App\Query;

class SearchService
{
    public function search(SearchQuery $query, ?AccountInterface $account): SearchResultSet;
}
```

#### Value Objects

**`SearchQuery`** (`src/Query/SearchQuery.php`):
- `query: string` — search terms (empty string triggers "recent items" fallback)
- `communityId: string`
- `filters: array` — optional key-value filters (e.g., `knowledge_type`)
- `page: int` (default 1)
- `pageSize: int` (default 20)

**`SearchResultSet`** (`src/Query/SearchResultSet.php`):
- `items: SearchResultItem[]`
- `totalHits: int`
- `totalPages: int`

**`SearchResultItem`** (`src/Query/SearchResultItem.php`):
- `id: string`
- `title: string`
- `summary: string`
- `knowledgeType: KnowledgeType`
- `score: float` (0-1 normalized)

#### Search Flow

1. If `query` is empty, load recent items by `created_at` desc, apply access control, return.
2. Run `Fts5SearchProvider::search()` with the query string, filtered to the community.
3. Run `EmbeddingProviderInterface::search()` with the query string and community ID.
4. Normalize scores within each result set to 0-1 range (min-max normalization; single-result sets get score 1.0).
5. Merge results by entity ID. For items appearing in both sets: `score = 0.6 * semantic + 0.4 * fulltext`. For items in only one set, use that score with the appropriate weight.
6. Sort by merged score descending.
7. Filter results through `KnowledgeItemAccessPolicy::access()` using the requesting account. Unauthenticated requests (`$account = null`) see only Public-tier items.
8. Paginate and return `SearchResultSet`.

#### KnowledgeItem as SearchIndexable

`KnowledgeItem` implements `SearchIndexableInterface`:

```php
public function getSearchDocumentId(): string
{
    return 'knowledge_item:' . $this->getId();
}

public function toSearchDocument(): array
{
    return [
        'title' => $this->getTitle(),
        'content' => $this->getContent(),
    ];
}

public function toSearchMetadata(): array
{
    return [
        'entity_type' => 'knowledge_item',
        'community_id' => $this->getCommunityId(),
        'knowledge_type' => $this->getKnowledgeType()->value,
        'access_tier' => $this->getAccessTier()->value,
    ];
}
```

Items are indexed on save (via `KnowledgeItemRepository::save()`).

---

### Q&A Service

**Location:** `src/Query/QaService.php`

RAG-based question answering grounded in a community's knowledge base.

#### Interface

```php
namespace App\Query;

class QaService
{
    public function __construct(
        private SearchService $searchService,
        private LlmProviderInterface $llmProvider,
    ) {}

    public function ask(string $question, string $communityId, ?AccountInterface $account): QaResponse;
}
```

#### Value Object

**`QaResponse`** (`src/Query/QaResponse.php`):
- `answer: string` — the generated response
- `citedItemIds: string[]` — IDs of knowledge items referenced
- `noRelevantItems: bool` — true when search returned no results

#### Q&A Flow

1. Build a `SearchQuery` with the question, community ID, page 1, pageSize 5.
2. Call `SearchService::search()` with the account for access control.
3. If no results, return `QaResponse` with `noRelevantItems = true` and a stock message: "I don't have enough information in this community's knowledge base to answer that question."
4. Build context from retrieved items using `KnowledgeItem::toMarkdown()`, each prefixed with its ID.
5. Call `LlmProviderInterface::complete()` with:
   - **System prompt:** "You are a knowledge assistant for an Indigenous community. Answer the question using ONLY the provided context. Cite sources by their item ID in square brackets (e.g., [item-123]). If the context does not contain enough information to answer, say so. Never fabricate information."
   - **User prompt:** The context block followed by the question.
6. Parse the response to extract cited item IDs (regex for `\[item-[^\]]+\]` patterns).
7. Return `QaResponse` with answer, cited IDs, and `noRelevantItems = false`.

#### LLM Provider Resolution

`QaService` depends only on `LlmProviderInterface`. The DI container resolves the concrete implementation. When sovereignty-aware routing is needed later, a `SovereigntyAwareLlmProvider` decorator can wrap the base provider and route based on `Community::getSovereigntyProfile()` without changing `QaService`.

---

## Unit 2: Reports + Export (#12, #13)

### Report Generator

**Location:** `src/Query/Report/`

#### Interfaces

```php
namespace App\Query\Report;

interface ReportRendererInterface
{
    public function render(Community $community, array $knowledgeItems, DateRange $dateRange): string;
    public function getType(): string;
}
```

**`DateRange`** (`src/Query/Report/DateRange.php`):
- `from: \DateTimeImmutable`
- `to: \DateTimeImmutable`

#### Report Service

**`ReportService`** (`src/Query/Report/ReportService.php`):

```php
class ReportService
{
    public function generate(
        string $reportType,
        Community $community,
        DateRange $dateRange,
        AccountInterface $account,
    ): string;
}
```

Flow:
1. Resolve the `ReportRendererInterface` implementation by type string.
2. Check access: `governance_summary` requires Staff+, `language_report` requires Member+, `land_brief` requires KnowledgeKeeper+.
3. Load KnowledgeItems for the community, filtered by date range and the knowledge types relevant to the report.
4. Apply access control: filter items the requesting account can see.
5. Call `renderer->render()` and return the Markdown string.
6. If no items match, return a report with an empty-state message rather than failing.

#### Report Implementations

**`GovernanceSummaryReport`** (`src/Query/Report/GovernanceSummaryReport.php`):
- Filters: `KnowledgeType::Governance`
- Access: Staff+
- Sections: title/date header, executive summary (item count + date range), item list with titles and summaries, grouped by sub-topic if metadata allows

**`LanguageReport`** (`src/Query/Report/LanguageReport.php`):
- Filters: `KnowledgeType::Cultural`
- Access: Member+
- Sections: title/date header, cultural items summary, item list with titles and summaries

**`LandBriefReport`** (`src/Query/Report/LandBriefReport.php`):
- Filters: `KnowledgeType::Land`
- Access: KnowledgeKeeper+
- Sections: title/date header, land-related items summary, item list with titles and summaries

Each renderer builds Markdown directly using string concatenation/interpolation. No templating engine.

---

### Export Service

**Location:** `src/Export/ExportService.php`

Produces a ZIP archive containing the community's full dataset in open, portable formats.

#### Interface

```php
namespace App\Export;

class ExportService
{
    public function export(Community $community, AccountInterface $account): string; // returns path to ZIP
}
```

#### Access Control

Admin role required. Checked at the start of `export()`. Throws `AccessDeniedException` for non-admins.

#### Archive Structure

```
{community-slug}-export-{YYYY-MM-DD}/
├── community.yaml          # Community config + wiki schema
├── knowledge-items/
│   ├── {uuid}.md           # Each item as Markdown with YAML frontmatter
│   └── ...
├── embeddings.json          # Vector data: [{entity_id, vector, metadata}]
├── media/
│   ├── {original-filename}  # Original uploaded files
│   └── ...
├── users.yaml               # Community members: [{name, roles}] — no password hashes
└── README.md                # Migration instructions + format version
```

#### Knowledge Item Markdown Format

```markdown
---
id: {uuid}
title: {title}
knowledge_type: {type}
access_tier: {tier}
allowed_roles: [{roles}]
allowed_users: [{users}]
source_media: [{filenames}]
created_at: {timestamp}
updated_at: {timestamp}
---

{content}
```

#### Export Flow

1. Verify admin access.
2. Create a temp directory with the archive structure.
3. Serialize `Community` to `community.yaml` (name, slug, locale, sovereignty_profile, contact_email, wiki_schema).
4. Load all `KnowledgeItem` entities for the community. Write each as a Markdown file with YAML frontmatter.
5. Dump embeddings from `SqliteEmbeddingStorage` for the community to `embeddings.json`.
6. Copy original media files from the media library.
7. Build `users.yaml` from community member accounts (name + roles only).
8. Write `README.md` with format version, generation timestamp, and import instructions.
9. ZIP the directory and return the path.

---

### Import Service

**Location:** `src/Export/ImportService.php`

Handles the core round-trip: community config + knowledge items. Media files are re-linked to storage. Embeddings are not imported (regenerated via pipeline).

#### Interface

```php
namespace App\Export;

class ImportService
{
    public function import(string $archivePath, AccountInterface $account): ImportResult;
}
```

**`ImportResult`** (`src/Export/ImportResult.php`):
- `communityId: string`
- `itemsImported: int`
- `mediaLinked: int`
- `warnings: string[]` (e.g., "embeddings.json skipped, will regenerate")

#### Import Flow

1. Verify admin access.
2. Extract ZIP to temp directory.
3. Parse `community.yaml` → create or update Community entity (match by slug).
4. Parse each `knowledge-items/*.md` → create KnowledgeItem entities with frontmatter metadata.
5. Copy `media/*` files into the media library, re-link to items via `source_media` frontmatter.
6. Skip `embeddings.json` (log a warning; embeddings regenerate when items are re-processed through the pipeline).
7. Skip `users.yaml` (user provisioning is out of scope; log a warning).
8. Return `ImportResult` with counts and warnings.

#### Round-Trip Testing

The acceptance criterion requires export-then-import verification. Test approach:
- Create a community with known items.
- Export via `ExportService`.
- Import into a fresh context via `ImportService`.
- Assert: community config matches, item count matches, item content matches, media files present.
- Embeddings verified absent (warning logged) and regenerable.

---

## Issue #7: Audio/Video Ingestion Handler

**Location:** `src/Ingestion/Handler/MediaIngestionHandler.php`

#### Supported MIME Types

Audio: `audio/mpeg`, `audio/mp4`, `audio/wav`, `audio/ogg`
Video: `video/mp4`, `video/quicktime`, `video/webm`

#### Interface

Implements the existing `IngestionHandlerInterface` and registers with `IngestionHandlerRegistry` for the above MIME types.

#### Flow

1. Validate file size <= 2GB. Throw `FileSizeExceededException` if over.
2. Store the original file in the media library via `FileRepositoryInterface`.
3. Dispatch an async `TranscribeJob` to the queue (via `waaseyaa/queue`).
4. Return a `RawDocument` with:
   - `rawText: ''` (empty, pending transcription)
   - `mimeType`: the original MIME type
   - `originalFilename`: preserved
   - `mediaId`: the stored media ID
   - `metadata: ['transcription_status' => 'pending']`

#### TranscribeJob

**Location:** `src/Ingestion/Job/TranscribeJob.php`

Async job that:
1. Retrieves the media file from storage.
2. Calls `TranscribeStep` (from the existing pipeline) to extract text.
3. Updates the KnowledgeItem's content with the transcription.
4. Sets `metadata.transcription_status = 'completed'`.
5. Optionally triggers the rest of the CompilationPipeline (Classify → Structure → Link → Embed).

Error handling: on failure, sets `transcription_status = 'failed'` with an error message. Does not retry automatically (admin intervention expected).

---

## Service Provider Wiring

`AppServiceProvider` additions:

```php
// Search indexing: index KnowledgeItems on save
// (hook into KnowledgeItemRepository::save())

// Register services
$container->singleton(SearchService::class, fn () => new SearchService(
    $container->get(Fts5SearchProvider::class),
    $container->get(EmbeddingProviderInterface::class),
    $container->get(KnowledgeItemAccessPolicy::class),
    $container->get(KnowledgeItemRepository::class),
));

$container->singleton(QaService::class, fn () => new QaService(
    $container->get(SearchService::class),
    $container->get(LlmProviderInterface::class),
));

$container->singleton(ReportService::class, fn () => new ReportService([
    new GovernanceSummaryReport(),
    new LanguageReport(),
    new LandBriefReport(),
]));

$container->singleton(ExportService::class, /* ... */);
$container->singleton(ImportService::class, /* ... */);

// Register MediaIngestionHandler with IngestionHandlerRegistry
```

---

## File Inventory

### New Files

| File | Purpose |
|------|---------|
| `src/Query/SearchService.php` | Hybrid search orchestration |
| `src/Query/SearchQuery.php` | Search request value object |
| `src/Query/SearchResultSet.php` | Search response value object |
| `src/Query/SearchResultItem.php` | Single search hit |
| `src/Query/QaService.php` | RAG question answering |
| `src/Query/QaResponse.php` | Q&A response value object |
| `src/Query/Report/ReportService.php` | Report orchestration |
| `src/Query/Report/ReportRendererInterface.php` | Report renderer contract |
| `src/Query/Report/DateRange.php` | Date range value object |
| `src/Query/Report/GovernanceSummaryReport.php` | Governance report renderer |
| `src/Query/Report/LanguageReport.php` | Language/cultural report renderer |
| `src/Query/Report/LandBriefReport.php` | Land brief report renderer |
| `src/Export/ExportService.php` | ZIP archive export |
| `src/Export/ImportService.php` | Archive import (core path) |
| `src/Export/ImportResult.php` | Import result value object |
| `src/Ingestion/Handler/MediaIngestionHandler.php` | Audio/video handler |
| `src/Ingestion/Job/TranscribeJob.php` | Async transcription job |

### Modified Files

| File | Change |
|------|--------|
| `src/Entity/KnowledgeItem/KnowledgeItem.php` | Implement `SearchIndexableInterface` |
| `src/Entity/KnowledgeItem/KnowledgeItemRepository.php` | Index on save |
| `src/AppServiceProvider.php` | Register new services and handlers |

### Test Files

| File | Covers |
|------|--------|
| `tests/Unit/Query/SearchServiceTest.php` | Hybrid scoring, access filtering, empty query fallback |
| `tests/Unit/Query/QaServiceTest.php` | RAG flow, citation parsing, no-results handling |
| `tests/Unit/Query/Report/ReportServiceTest.php` | Type resolution, access control, date filtering |
| `tests/Unit/Query/Report/GovernanceSummaryReportTest.php` | Governance render output |
| `tests/Unit/Query/Report/LanguageReportTest.php` | Language render output |
| `tests/Unit/Query/Report/LandBriefReportTest.php` | Land brief render output |
| `tests/Unit/Export/ExportServiceTest.php` | Archive structure, content serialization |
| `tests/Unit/Export/ImportServiceTest.php` | Round-trip verification |
| `tests/Unit/Ingestion/Handler/MediaIngestionHandlerTest.php` | MIME validation, size limit, job dispatch |
| `tests/Unit/Ingestion/Job/TranscribeJobTest.php` | Transcription flow, error handling |

---

## Acceptance Criteria Mapping

### #10 — Full-text and semantic search
- [x] Hybrid scoring (0.6 semantic + 0.4 full-text)
- [x] Access-controlled results (Public tier visible to unauthenticated)
- [x] Community-scoped
- [x] Empty query falls back to recent items
- [x] Response includes id, title, summary, knowledge_type, score

### #11 — Q&A interface
- [x] Embed question → semantic search top-5 → context prompt → LLM → answer with citations
- [x] Answers grounded in retrieved items only (system prompt enforces)
- [x] Citations as item IDs
- [x] Graceful "not found" when no relevant items
- [x] Access-controlled context
- [x] LLM provider via DI (sovereignty routing deferred)

### #12 — Report generator
- [x] Three report types: governance_summary, language_report, land_brief
- [x] PHP render classes (no Twig)
- [x] Type-specific knowledge type filtering
- [x] Role-based access per report type
- [x] Date range filtering
- [x] Valid Markdown output
- [x] Graceful empty states

### #13 — Export tooling
- [x] Archive: community.yaml, knowledge-items/*.md, embeddings.json, media/*, users.yaml, README
- [x] Admin-only access
- [x] No password hashes in users.yaml
- [x] Round-trip testing (export then core import)
- [x] Import: community + items + media re-link; embeddings regenerated via pipeline

### #7 — Audio/video ingestion handler
- [x] Accepts audio (MP3, M4A, WAV, OGG) and video (MP4, MOV, WebM) MIME types
- [x] 2GB size limit enforced
- [x] Stores original in media library
- [x] Dispatches async TranscribeJob
- [x] Returns RawDocument with empty rawText and transcription_status = 'pending'

### #27 — MVP acceptance scenario (closed by #10 + #11)
- [x] Semantic search over ingested documents
- [x] Multi-source Q&A with provenance
