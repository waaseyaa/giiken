# Giiken Boot-to-Browser Design

**Date:** 2026-04-05
**Status:** Design approved, awaiting plan
**Goal:** Render the Discovery homepage at `http://127.0.0.1:8765/test-community` end-to-end with no hacks, no stubs-that-persist, and no framework bypasses.

## Problem

Giiken's Phase 4 frontend is complete (173 PHP tests pass, Vite production build works, all Vue pages exist), and framework PR #1114 wired Vite asset injection into Inertia's root template. But `vendor/bin/waaseyaa serve` cannot serve a single request:

- Every HTTP response is `200` with an empty body.
- The server log shows `Boot failed: Database not found at .../storage/waaseyaa.sqlite. In production, the database must already exist.`
- Even after the database is present, controllers will fail because the DI container has no bindings for `SearchService`, `QaService`, `CommunityRepositoryInterface`, `KnowledgeItemRepositoryInterface`, `ReportService`, `ExportService`, or `ImportService`.
- No community is seeded, so `findBySlug('test-community')` would return null.

## Root cause analysis

Investigation surfaced five distinct problems. The handoff prompt identified three; two more were found during exploration:

### 1. `public/index.php` is empty

This is the real source of the `200 empty body` response. PHP's built-in server happily serves an empty file. Nothing instantiates `HttpKernel`, so HttpKernel's own 500-on-boot-failure path (HttpKernel.php:63-70) never runs. The `Boot failed` log line is coming from a different boot path (likely a CLI command), not from the HTTP request handling.

**The handoff prompt attributed the empty response to framework issue #1117 (boot exceptions should return 500). That is real but secondary — the primary issue is that the HTTP front controller does not exist.**

### 2. `APP_ENV` defaults to `production`

`config/waaseyaa.php:10` reads `getenv('APP_ENV') ?: 'production'`. `DatabaseBootstrapper::guardMissingProductionSqliteDatabase` throws only in production; in dev it auto-creates the SQLite file (DatabaseBootstrapper.php:58). So the "database not found" error disappears the moment we run under `APP_ENV=development`. This is a framework DX gap (waaseyaa/framework#1116) — `serve` should not drop the user into production mode by default.

### 3. Entity tables are never created

`entity-storage/SqlSchemaHandler::ensureTable()` (entity-storage/src/SqlSchemaHandler.php:31) can build tables from an `EntityType` spec, but nothing calls it automatically at boot. The framework's `Migrator` / `MigrationLoader` (foundation/src/Migration/) discovers migrations from `{basePath}/migrations/*.php` and from each package manifest, and `vendor/bin/waaseyaa migrate` already runs them. Giiken has no `migrations/` directory.

### 4. `GiikenServiceProvider::register()` binds nothing

The DI container is already available — the base `ServiceProvider` class exposes `singleton()` and `bind()` (foundation/src/ServiceProvider/ServiceProvider.php). The service provider just has a TODO block listing every service that needs wiring:

```php
// Phase 3 service wiring — deferred until Waaseyaa DI container is ready:
// SearchService(Fts5SearchProvider, EmbeddingProviderInterface, ...)
// QaService(SearchService, LlmProviderInterface)
// ReportService([...], KnowledgeItemRepositoryInterface)
// ExportService(...)
// ImportService(...)
// MediaIngestionHandler(...) -> register with IngestionHandlerRegistry
```

### 5. No seeded community

The Discovery routes match on `{communitySlug}`. Without a row in the community table, every request 404s (or worse, NPEs) the moment `CommunityRepository::findBySlug('test-community')` returns null.

## Approach: three PRs

### PR A — Framework DX fixes (`waaseyaa/framework`)

Four small, focused changes:

**1a. `ServeCommand` sets dev env by default.**
When launching the child PHP server, merge the parent environment with `APP_ENV=development` and `APP_DEBUG=1` *unless the caller has already set `APP_ENV`*. Switch `proc_open` from inheriting env to an explicit env array. Print a one-line notice at startup:

> Starting in development mode (APP_ENV=development). Use `APP_ENV=production vendor/bin/waaseyaa serve` to override.

Closes waaseyaa/framework#1116.

**1b. `ServeCommand` verifies `public/index.php` is usable.**
Before spawning the server, check `public/index.php` exists and is non-empty. If not, print a clear error pointing at `vendor/bin/waaseyaa make:public` and exit 1. No silent empty-body failures.

Addresses part of waaseyaa/framework#1117 (the empty-body symptom, specifically).

**1c. Ship `public/index.php` template + `make:public` command.**
Add `packages/cli/src/Command/Make/MakePublicCommand.php` that writes a canonical front controller to `public/index.php`. The template instantiates `HttpKernel`, calls `handle()`, and sends the response. An integration test verifies the generated file boots a minimal app without error. `InstallCommand` invokes `make:public` unconditionally, so newly-installed projects get a working front controller for free.

**1d. Ship `NullLlmProvider`.**
Add `packages/ai-agent/src/Provider/NullLlmProvider.php` implementing the framework's LLM provider interface. `complete()` returns a deterministic string:

> "[LLM unavailable in this environment — configure an LLM provider to enable AI features.]"

Zero network, zero config. Technically callable anywhere, but documented as dev-only. Exists so apps can wire the interface during development without depending on Ollama or OpenAI.

**PR A test coverage:**

- `ServeCommandTest` — unit test for env merge (respects caller-set `APP_ENV`, defaults to `development`, prints notice).
- `MakePublicCommandTest` — integration test: run the command, assert file exists, assert generated file boots a minimal kernel without throwing.
- `NullLlmProviderTest` — unit test: deterministic output, no network I/O.

### PR B — Giiken boot + wiring (`waaseyaa/giiken`)

**2a. `public/index.php`.**
Generated by running `vendor/bin/waaseyaa make:public` from PR A. Not hand-written — consuming the framework template is the acceptance test for PR A.

**2b. `migrations/001_ensure_entity_tables.php`.**
A `Waaseyaa\Foundation\Migration\Migration` whose `up()` iterates every registered `EntityType` from the kernel's `EntityTypeManager` and calls the entity-storage schema handler to materialize each table (`community`, `knowledge_item`, `wiki_lint_report`, plus any framework-owned types the kernel registers). `down()` drops them in reverse. Discovered automatically by `MigrationLoader` via the `{basePath}/migrations` convention. Run via `vendor/bin/waaseyaa migrate`.

**Open question addressed at plan-writing time:** The framework's `Migration` load pattern loads files via `require` as plain factories. The migration will need access to `EntityTypeManager` + `SqlSchemaHandler` at `up()` time. If the framework has no clean pattern for this, PR A grows by one class: `Waaseyaa\Foundation\Migration\EntitySchemaMigration` base class that receives the manager/handler via `setContext()` from the `Migrator`. See Risk A.

**2c. `GiikenServiceProvider::register()` — real service wiring.**
Replace the TODO block with bindings. Final interfaces confirmed while writing the plan; expected shape:

```php
$this->singleton(CommunityRepositoryInterface::class, fn () =>
    new CommunityRepository($this->kernelResolver('entity.repository.community')));

$this->singleton(KnowledgeItemRepositoryInterface::class, fn () =>
    new KnowledgeItemRepository($this->kernelResolver('entity.repository.knowledge_item')));

$this->singleton(SearchProviderInterface::class, fn () =>
    new Fts5SearchProvider($this->kernelResolver(DatabaseInterface::class)));

$this->singleton(Giiken\Pipeline\Provider\EmbeddingProviderInterface::class, fn () =>
    new FakeEmbeddingAdapter(new \Waaseyaa\AiVector\Testing\FakeEmbeddingProvider()));

$this->singleton(Giiken\Pipeline\Provider\LlmProviderInterface::class, fn () =>
    new NullLlmAdapter(new \Waaseyaa\AiAgent\Provider\NullLlmProvider()));

$this->singleton(SearchService::class, fn () => new SearchService(
    $this->resolve(SearchProviderInterface::class),
    $this->resolve(Giiken\Pipeline\Provider\EmbeddingProviderInterface::class),
    new KnowledgeItemAccessPolicy(),
    $this->resolve(KnowledgeItemRepositoryInterface::class),
));

$this->singleton(QaServiceInterface::class, fn () => new QaService(
    $this->resolve(SearchService::class),
    $this->resolve(Giiken\Pipeline\Provider\LlmProviderInterface::class),
));

// ReportService, ExportService, ImportService follow the same pattern — their
// constructor signatures are documented in src/GiikenServiceProvider.php's
// existing Phase 3 TODO block and will be wired verbatim. Elided here for brevity.
$this->singleton(ReportService::class, /* see TODO block */);
$this->singleton(ExportService::class, /* see TODO block */);
$this->singleton(ImportService::class, /* see TODO block */);
```

Two tiny Giiken-local adapter classes in `src/Pipeline/Provider/Adapter/` bridge the framework's provider interfaces to Giiken's local `Pipeline\Provider\*Interface`:

- `FakeEmbeddingAdapter` — wraps `\Waaseyaa\AiVector\Testing\FakeEmbeddingProvider`
- `NullLlmAdapter` — wraps `\Waaseyaa\AiAgent\Provider\NullLlmProvider`

Each is ~20 lines, real, not a stub. They exist because Giiken chose to define its own provider interfaces in `Giiken\Pipeline\Provider\` rather than depending on framework interfaces directly — the adapters are the bridge.

**2d. `src/Console/SeedTestCommunityCommand.php`.**
A real Symfony console command (`giiken:seed:test-community`), registered via `GiikenServiceProvider::commands()`, that:

- Builds a `Community` entity with `slug='test-community'`, `name='Test Community'`, a default `WikiSchema` (default_language='en', all five `KnowledgeType` cases enabled, default llm_instructions).
- Saves via `CommunityRepository`.
- Creates 2-3 sample `KnowledgeItem` records at `AccessTier::Public` so the homepage has visible content (hero + knowledge cards).
- Idempotent: if a community with that slug already exists, reports the existing ID and exits 0.

**PR B test coverage:**

- `EnsureEntityTablesMigrationTest` — unit test: run `up()` against an in-memory SQLite, assert all registered entity-type tables exist.
- `SeedTestCommunityCommandTest` — unit test: run the command twice, assert idempotency, assert a community with slug `test-community` exists with sample items.
- `DiscoveryHomepageBootTest` — **integration acceptance test**: boot the kernel against in-memory SQLite, run migrations, run the seed command, dispatch `GET /test-community`, assert the response is 200 with an Inertia page object whose `component` is `Discovery/Index` and whose props contain `community.slug === 'test-community'` and non-empty `recentItems`. This is the definition of done.

## Execution order

Giiken consumes the framework via Composer path repositories (`repositories` section in `composer.json` points at `/home/jones/dev/waaseyaa/packages/*` with `symlink: true`). No publish cycle — the workflow is:

1. **Framework worktree first.** Create a worktree off `main` in `/home/jones/dev/waaseyaa`, land all PR A changes, run framework tests, open the PR. The symlinked path repo makes the new framework code visible to Giiken immediately.
2. **Giiken worktree second.** Create a worktree off `main` in `/home/jones/dev/giiken`, run `vendor/bin/waaseyaa make:public` (consuming the fresh framework), write the migration, wire the service provider, add the seed command, run PHPUnit, open the PR.
3. **Verify end-to-end.** In the Giiken worktree: `vendor/bin/waaseyaa migrate && vendor/bin/waaseyaa giiken:seed:test-community && vendor/bin/waaseyaa serve`. Then `curl http://127.0.0.1:8765/test-community` and inspect the HTML for Vite asset tags and the `Discovery/Index` Inertia payload.

Both PRs open simultaneously. PR A merges first, PR B merges after rebase if needed.

## Verification gates (definition of done)

| Gate | How |
|---|---|
| Framework unit tests pass | `composer test` in waaseyaa worktree |
| `make:public` produces a bootable file | new integration test in PR A |
| `NullLlmProvider` returns deterministic string | new unit test in PR A |
| `ServeCommand` env merge respects caller overrides | new unit test in PR A |
| Giiken unit tests still pass (173+) | `./vendor/bin/phpunit` in giiken worktree |
| Migration creates all entity tables | `EnsureEntityTablesMigrationTest` in PR B |
| Seed command is idempotent | `SeedTestCommunityCommandTest` in PR B |
| Browser acceptance: `curl http://127.0.0.1:8765/test-community` returns 200, HTML contains `<script type="module" src="/build/assets/...js"`, inline Inertia `data-page` contains `"component":"Discovery\/Index"` with `community.slug = "test-community"` | `DiscoveryHomepageBootTest` in PR B + manual curl documented in PR description |
| No stubs, no TODOs, no hand-created empty files, no bypasses | diff review during PR review |

## File layout

### Framework PR A

```
packages/cli/src/Command/ServeCommand.php                     (modify: env injection + file verify)
packages/cli/src/Command/Make/MakePublicCommand.php           (new)
packages/cli/src/Command/InstallCommand.php                   (modify: call make:public)
packages/cli/templates/public/index.php.stub                  (new)
packages/cli/tests/Unit/Command/ServeCommandTest.php          (new)
packages/cli/tests/Integration/MakePublicCommandTest.php      (new)
packages/ai-agent/src/Provider/NullLlmProvider.php            (new)
packages/ai-agent/tests/Unit/Provider/NullLlmProviderTest.php (new)
```

### Giiken PR B

```
public/index.php                                                   (new, via make:public)
migrations/001_ensure_entity_tables.php                            (new)
src/GiikenServiceProvider.php                                      (modify: delete TODO block, add register bindings + commands())
src/Pipeline/Provider/Adapter/FakeEmbeddingAdapter.php             (new, ~20 lines)
src/Pipeline/Provider/Adapter/NullLlmAdapter.php                   (new, ~20 lines)
src/Console/SeedTestCommunityCommand.php                           (new)
tests/Integration/Boot/DiscoveryHomepageBootTest.php               (new — acceptance test)
tests/Unit/Console/SeedTestCommunityCommandTest.php                (new)
tests/Unit/Migration/EnsureEntityTablesMigrationTest.php           (new)
```

## Out of scope (deferred)

All deferred items have GitHub issues filed so nothing is lost:

| Item | Issue |
|---|---|
| Framework alternative: auto-generate entity-storage schema migrations from registered EntityTypes | waaseyaa/framework#1118 |
| Giiken: wire MediaIngestionHandler (Phase 2 ingestion TODO) | waaseyaa/giiken#39 |
| Giiken: replace NullLlmProvider/FakeEmbeddingProvider with real Ollama/OpenAI wiring per sovereignty profile | waaseyaa/giiken#40 |
| Giiken: session-based auth for Discovery and Management routes (enables non-public tier visibility) | waaseyaa/giiken#41 |
| Framework: boot-exception visibility (complementary to PR A's front-controller verification) | waaseyaa/framework#1117 (existing) |
| Framework: serve auto-create dev database (resolved by PR A's env merge) | waaseyaa/framework#1116 (existing, closed by PR A) |

## Risks and mitigations

**Risk A — `Migration` load pattern.** The framework loads migrations via `require` as plain factories. The migration in 2b needs `EntityTypeManager` and `SqlSchemaHandler` at `up()` time. If no clean pattern exists, PR A grows by one class: a `Waaseyaa\Foundation\Migration\EntitySchemaMigration` base that the `Migrator` injects context into before calling `up()`. Mitigation: verify the pattern during plan writing; if uncertain, split Giiken's migration into "raw SQL" (schemas hard-coded) as a safe fallback.

**Risk B — Giiken vs framework provider interfaces.** `Giiken\Pipeline\Provider\EmbeddingProviderInterface` and `LlmProviderInterface` are Giiken-local interfaces, not the framework's. The adapter approach in 2c handles this cleanly, but it does mean the framework's `NullLlmProvider` cannot be bound directly — it's always wrapped. Accepted as a cost of Giiken owning its own interfaces.

**Risk C — `Fts5SearchProvider` may not exist.** Investigation saw `SearchProviderInterface` referenced in Giiken's `SearchService` constructor but did not confirm a concrete `Fts5SearchProvider` class ships in `waaseyaa/search`. If the concrete class is missing, options:

1. PR A grows to ship `Fts5SearchProvider` in `waaseyaa/search` (cleanest).
2. Giiken ships a local `KnowledgeItemFtsSearchProvider` in `src/Query/` that satisfies `SearchProviderInterface` directly.

Decision deferred to plan-writing step after a targeted check of the search package.

**Risk D — `EntityRepositoryInterface` resolution for Giiken repositories.** `CommunityRepository` constructor takes `EntityRepositoryInterface` from `waaseyaa/entity`. Those are entity-type-specific and come from the `EntityTypeManager`. The binding uses `$this->kernelResolver('entity.repository.community')` — the exact container key is unverified. Plan-writing step will confirm by reading framework entity provider registration.

## Success criteria

`vendor/bin/waaseyaa serve` started in `/home/jones/dev/giiken`, then opening `http://127.0.0.1:8765/test-community` in a browser shows the Discovery homepage with the indigo gradient hero, search input, and knowledge cards (from the seeded items). No errors in the server log. No manual workarounds. The `DiscoveryHomepageBootTest` passes in CI.
