# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Giiken is a sovereign indigenous knowledge management platform built on the **Waaseyaa** custom PHP framework. It implements community-based RBAC for multi-tenant knowledge governance.

**PHP:** 8.4+ | **License:** GPL-2.0-or-later | **Namespace:** `Giiken\` (PSR-4)

## Commands

```bash
# Dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Unit/Access/KnowledgeItemAccessPolicyTest.php

# Run a specific test suite
./vendor/bin/phpunit --testsuite Unit

# Static analysis
./vendor/bin/phpstan analyse src/
```

## Architecture

### Framework

Waaseyaa is a modular PHP framework split into 30+ packages (`waaseyaa/*`). Key packages used here:

- `waaseyaa/entity` — `ContentEntityBase`, `EntityRepositoryInterface`, `EntityTypeManager`
- `waaseyaa/access` — `AccessPolicyInterface`, `PolicyAttribute`
- `waaseyaa/foundation` — Service provider bootstrap, `WaaseyaaRouter`
- `waaseyaa/ai-pipeline` — `PipelineContext`, `PipelineStepInterface`, `StepResult`
- `waaseyaa/ingestion` — File upload and MIME-type routing
- `waaseyaa/media` — File storage (`FileRepositoryInterface`)

### Entity Pattern

All domain objects extend `ContentEntityBase`. Properties are accessed via `$this->get('key')` — define typed getter methods on top of that. Repositories wrap `EntityRepositoryInterface` with typed query methods and set `updated_at` automatically on save.

Three entity types are registered in `GiikenServiceProvider`:
- `community` → `Entity\Community\Community` (multi-tenant container, owns a `WikiSchema`)
- `knowledge_item` → `Entity\KnowledgeItem\KnowledgeItem` (primary domain object, implements `HasCommunity`)
- `wiki_lint_report` → `Wiki\WikiLintReport` (stores lint findings per community)

### RBAC / Multi-Tenancy

Community membership is encoded as account roles using the pattern `giiken.community.{communityId}.{roleSlug}`. The `CommunityRole` enum defines five roles with a numeric rank hierarchy: Admin (5) > KnowledgeKeeper (4) > Staff (3) > Member (2) > Public (1).

`KnowledgeItemAccessPolicy` (annotated with `#[PolicyAttribute('knowledge_item')]`) evaluates access by parsing these account roles and comparing against the item's `AccessTier` (Public / Members / Staff / Restricted). Restricted items also support explicit `allowed_roles` and `allowed_users` lists.

Access resolution logic:
- Admin → always allowed
- Public tier → always allowed
- Members tier → role rank >= Member (2)
- Staff tier → role rank >= Staff (3)
- Restricted → role rank >= KnowledgeKeeper (4) OR listed in `allowed_roles`/`allowed_users`

### Compilation Pipeline

`Pipeline\CompilationPipeline` runs a 5-step sequential pipeline for AI-powered document processing:

1. **TranscribeStep** — Extract text from media
2. **ClassifyStep** — LLM classification into `KnowledgeType` (Cultural, Governance, Land, Relationship, Event)
3. **StructureStep** — LLM-powered content structuring
4. **LinkStep** — Embedding-based link discovery between items
5. **EmbedStep** — Vector embedding generation for search

Each step implements `PipelineStepInterface` from `waaseyaa/ai-pipeline`. Steps receive and pass a `PipelineContext` and return a `StepResult`. The pipeline uses two provider interfaces: `LlmProviderInterface` (`complete(systemPrompt, userContent)`) and `EmbeddingProviderInterface`.

### Ingestion System

`Ingestion\IngestionHandlerRegistry` routes uploaded files to handlers by MIME type. Each handler produces a `RawDocument` from a file path.

Handlers: `CsvIngestionHandler`, `DocumentIngestionHandler` (Word/PDF via `MarkItDownConverter`), `HtmlIngestionHandler`, `MarkdownIngestionHandler`.

### Wiki Lint System

Per-community wiki validation. `WikiLintJob` runs all registered `LintCheckInterface` implementations against a community's knowledge items and produces a `WikiLintReport`.

Built-in checks: `BrokenLinkCheck`, `OrphanPageCheck`.

`WikiSchema` is a value object on `Community` that stores: `defaultLanguage`, `knowledgeTypes[]`, `llmInstructions`.

### Service Provider

`GiikenServiceProvider` registers entity types with `EntityTypeManager` and defines routes via `WaaseyaaRouter`. This is the entry point for adding new entity types or wiring new ingestion handlers.

## Testing Conventions

- PHPUnit 10.5+ with `#[Test]`, `#[CoversClass]`, and `#[DataProvider]` attributes
- Test fixtures built in `setUp()` using private helper methods for creating entities and mock accounts
- Access policy tests cover every combination of role × access tier
- Pipeline step tests mock `LlmProviderInterface` / `EmbeddingProviderInterface`
- Tests mirror `src/` structure: `tests/Unit/Access/`, `tests/Unit/Entity/`, `tests/Unit/Pipeline/`, `tests/Unit/Ingestion/`, `tests/Unit/Wiki/`
- Config: `phpunit.xml.dist` (bootstrap: `vendor/autoload.php`, suites: Unit, Integration)
