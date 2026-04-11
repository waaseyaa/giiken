# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Giiken is a sovereign indigenous knowledge management platform built on the **Waaseyaa** custom PHP framework. It implements community-based RBAC for multi-tenant knowledge governance.

**PHP:** 8.4+ | **License:** GPL-2.0-or-later | **Namespace:** `Giiken\` (PSR-4)

## Boot-to-browser status (as of 2026-04-11)

✅ **Phase A green.** Boot, migrations, seed, and SSR dispatch all work end-to-end on `waaseyaa/* ^0.1.0-alpha.120`.

Verified smoke path:

```
./bin/waaseyaa migrate                            # 1 migration applied
./bin/waaseyaa giiken:seed:test-community         # community + 3 knowledge items
./bin/waaseyaa serve                              # or php -S 127.0.0.1:8080 -t public public/index.php
curl http://127.0.0.1:8080/                       # 200, Inertia "Discover"
curl http://127.0.0.1:8080/test-community         # 200, Inertia "Discovery/Index" with seeded items
```

PHPUnit: 198/198 passing.

### Resolved (closed)

- waaseyaa/framework#1125 — cli bin entrypoint, `app.url` default, array-controller normalization (alpha.107).
- waaseyaa/framework#1127 — foundation→ssr dependency.
- giiken#42 — `GiikenServiceProvider` provider registrations.
- giiken#43 — entity schema migrations (`community`, `knowledge_item`, `wiki_lint_report`).
- giiken#44 — `giiken:seed:test-community` console command.

### `vendor/bin/waaseyaa` is symlinked to the giiken wrapper

`./bin/waaseyaa` is the giiken-local entry that loads `.env` and uses the project root. By default, composer installs `vendor/bin/waaseyaa` as a proxy to the `waaseyaa/cli` package's bin, which does **not** load `.env` and resolves `projectRoot` relative to its own vendor location — that path lands in `vendor/waaseyaa/cli/storage/waaseyaa.sqlite` and falls through to `APP_ENV=production`, tripping the `DatabaseBootstrapper` "must already exist" guard.

To make both invocations equivalent, `composer.json` runs a `post-install-cmd` / `post-update-cmd` that replaces `vendor/bin/waaseyaa` with a symlink to `../../bin/waaseyaa`. After any `composer install` / `composer update`, both `./bin/waaseyaa` and `./vendor/bin/waaseyaa` point at the same wrapper. This is a workaround for waaseyaa/framework#1226 — once that lands, the symlink hook can be removed.

## Commands

```bash
# Dependencies
composer install

# Common scripts
composer run dev
composer run test
composer run analyse

# Install git hooks
lefthook install

# Run all PHP tests
./vendor/bin/phpunit

# Run a single PHP test file
./vendor/bin/phpunit tests/Unit/Access/KnowledgeItemAccessPolicyTest.php

# Run a specific PHP test suite
./vendor/bin/phpunit --testsuite Unit

# Run frontend tests (Vitest + Vue Test Utils)
npm run test:js

# Run frontend tests in watch mode
npm run test:js:watch

# Static analysis
./vendor/bin/phpstan analyse src/

# Run hooks manually
lefthook run pre-commit
lefthook run pre-push
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

All domain objects extend `ContentEntityBase`. Properties are accessed via `$this->get('key')` (with `$casts` for enums, datetimes, and JSON lists) — define typed getter methods on top of that. Construct app/test instances with `EntityClass::make([...])` (or the domain constructor for `Community`); use `fromStorage()` when simulating `EntityInstantiator` / SQL hydration. Repositories wrap `EntityRepositoryInterface` with typed query methods and set `updated_at` (ISO-8601) on save.

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

### Lifecycle Documentation Governance

- Canonical runtime flow doc: `docs/architecture/lifecycle.md`
- CI enforces drift checks via `scripts/check-lifecycle-drift.sh`
- If lifecycle-impacting files change (`public/index.php`, `src/GiikenServiceProvider.php`, HTTP controllers/middleware, entities/query/pipeline code), update `docs/architecture/lifecycle.md` in the same PR.

## Testing Conventions

### Backend (PHPUnit)

- PHPUnit 10.5+ with `#[Test]`, `#[CoversClass]`, and `#[DataProvider]` attributes
- Test fixtures built in `setUp()` using private helper methods for creating entities and mock accounts
- Access policy tests cover every combination of role × access tier
- Pipeline step tests mock `LlmProviderInterface` / `EmbeddingProviderInterface`
- Tests mirror `src/` structure: `tests/Unit/Access/`, `tests/Unit/Entity/`, `tests/Unit/Pipeline/`, `tests/Unit/Ingestion/`, `tests/Unit/Wiki/`
- Config: `phpunit.xml.dist` (bootstrap: `vendor/autoload.php`, suites: Unit, Integration)

### Frontend (Vitest + Vue Test Utils)

- Vitest 3 with the `test` block in `vite.config.ts` (reuses the `@/` → `resources/js` alias).
- Environment: `happy-dom`. No global injections — import `describe`, `it`, `expect` from `vitest` explicitly.
- Test files live under `tests/js/` and are included via `tests/js/**/*.{test,spec}.ts`.
- `@vue/test-utils` `mount()` drives component rendering. Stub `@inertiajs/vue3`'s `Link` via `global.stubs.Link` with a plain anchor so tests don't need a router.
- Scripts: `npm run test:js` (one-shot) / `npm run test:js:watch`.
- Real-browser smoke tests live under `/tmp/giiken-puppeteer/` using puppeteer-core against system Chrome — those are complementary to Vitest, not a replacement.
