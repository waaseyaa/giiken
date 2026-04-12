# Giiken Application Lifecycle

This document describes how a request moves through Giiken at runtime, where app-level code hooks into Waaseyaa, and what invariants should remain true during refactoring.

## Scope

- App: `giiken`
- Framework: `waaseyaa/*`
- Entrypoint: `public/index.php`
- Primary app integration point: `src/AppServiceProvider.php`

## 1. Boot Lifecycle

### 1.1 Entrypoint

`public/index.php`:

1. **CLI-server static file guard:** When running under PHP's built-in server (`PHP_SAPI === 'cli-server'`), checks if the request maps to an existing file on disk and returns `false` to let the server serve it directly. No effect on production servers.
2. Loads Composer autoloader.
3. Loads `.env` with `Symfony Dotenv` (fails fast with HTTP 500 on parse/path error).
4. Instantiates `Waaseyaa\Foundation\Kernel\HttpKernel` with project root.
5. Calls `$kernel->handle()` and then `$response->send()`.

### 1.2 Kernel Boot Sequence

Inside `HttpKernel::handle()` -> `AbstractKernel::boot()`:

1. `EnvLoader::load()`
2. `ConfigLoader::load()`
3. Logger initialization from config
4. Safety guard: deny debug in non-development env
5. Core service bootstrap:
   - database
   - entity type manager
   - package manifest compile
   - migrations bootstrap
6. Provider discovery/register (`ProviderRegistry::discoverAndRegister`)
7. App entity type load + content type validation
8. Provider boot (`ProviderRegistry::boot`)
9. Access policy discovery
10. Finalization (`HttpKernel::finalizeBoot`)

### 1.3 Where Giiken Enters

The first Giiken app-level class in normal boot is `App\AppServiceProvider`:

- `register()` contributes app entity types (`community`, `knowledge_item`, `wiki_lint_report`)
- `register()` binds app services resolved by SSR `serviceResolver`: `CommunityRepositoryInterface`, `KnowledgeItemRepositoryInterface`, `SearchService`, `QaServiceInterface`, `ReportServiceInterface`, `ExportServiceInterface`, `SynthesisService`, dev `NullEmbeddingProvider` / `NullLlmProvider`, and a PSR-14 `EventDispatcherInterface` alias to the kernel dispatcher (for `EntityRepository` construction); registers `App\Http\Inertia\InertiaHttpResponder` (full-page renderer from DI when present)
- `register()` re-binds `InertiaFullPageRendererInterface` with a project-root-based `ViteAssetManager` (`public/build` manifest or `VITE_DEV_SERVER`), sets `Inertia::setVersion('giiken')`, and refreshes `Inertia::setRenderer(...)` with a custom template closure that rewrites the data-page attribute from `data-page="true"` to `data-page="app"` so Inertia v2's client-side reader (`script[data-page="app"]`) actually finds the initial page object — workaround for waaseyaa/framework#1227
- Frontend bundle: Vite entry `resources/js/app.ts`, production output under `public/build` (`npm run build`); set `VITE_DEV_SERVER` (e.g. `http://127.0.0.1:5173`) when using `npm run dev` for HMR
- `commands()` contributes CLI commands (`giiken:seed:test-community`)
- `routes()` contributes app HTTP routes (discovery, management, `GET`/`POST` `/login`, `GET` `/logout`)
- `HomeController::discover` (`GET /`) injects `CommunityRepositoryInterface` and ships the result of `findAll()` as the `communities` Inertia prop for `Pages/Discover.vue`, which renders a community card grid linking into `/{slug}` Discovery pages

### 1.4 Schema and local data

- App SQL migrations live in `migrations/` and run via `bin/waaseyaa migrate` during bootstrap when pending.
- Tables `community`, `knowledge_item`, and `wiki_lint_report` must exist before repository saves; optional demo data: `bin/waaseyaa giiken:seed:test-community` after migrate (also ensures demo `giiken_staff` user and community staff role when `EntityTypeManager` is available).

## 2. Request Lifecycle

### 2.1 High-level Request Path

After boot, `HttpKernel::serveHttpRequest()` executes:

1. CORS handling (`handleCors`)
2. Route match (`WaaseyaaRouter`)
3. Request object creation (`Request::createFromGlobals()`)
4. Middleware pipeline:
   - bearer auth
   - session middleware
   - CSRF
   - authorization
   - debug headers (if debug mode)
   - provider middleware
5. Account resolution (`_account` request attribute)
6. If the middleware pipeline returns a response whose status is **not** **200** (for example **302** login redirect or **401** JSON from `AuthorizationMiddleware`), the kernel returns it immediately and does not dispatch controllers. (Shipped in `waaseyaa/foundation` as of [framework#1180](https://github.com/waaseyaa/framework/pull/1180); bump Giiken’s lockfile after that release.)
7. Router dispatch (`ControllerDispatcher`)

### 2.2 App Route Registration

App routes are added through `AppServiceProvider::routes(...)`, including:

- Public landing (Inertia): `GET` `/` → `Discover` page (`HomeController::discover`)
- Session HTML auth (public): `GET`/`POST` `/login`, `GET` `/logout`
- Discovery:
  - `/{communitySlug}`
  - `/{communitySlug}/search`
  - `/{communitySlug}/ask`
  - `/{communitySlug}/item/{itemId}`
- Query API (JSON, CSRF-exempt; `POST` bodies are JSON):
  - `POST /api/v1/ask` (`_public`) — Q&A + structured citations
  - `POST /api/v1/report` (`_authenticated`) — markdown report + item count
  - `POST /api/v1/synthesis` (`_authenticated`) — save Q&A answer as `knowledge_type: synthesis` with capped access
- Management (`_authenticated`):
  - `/{communitySlug}/manage`
  - `/{communitySlug}/manage/reports`
  - `/{communitySlug}/manage/users`
  - `GET  /{communitySlug}/manage/ingestion` — Inertia page with upload form
  - `POST /{communitySlug}/manage/ingestion` — multipart upload, routed to `ManagementController::ingestUpload`, dispatched through `IngestionHandlerRegistry`
  - `/{communitySlug}/manage/export` (Inertia)
  - `GET /{communitySlug}/manage/export/download` — ZIP export (admin-only; enforced in `ExportService`)

### 2.3 Controller Dispatch Contract

SSR app controllers use the four-argument dispatch shape; they return **`Symfony\Component\HttpFoundation\Response`**, not raw `InertiaResponse`, so `SsrPageHandler` can emit HTML or JSON. Internally they call `Inertia::render(...)` and pass the result through `InertiaHttpResponder::toResponse()`.

```php
public function action(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
```

Symfony `HttpRequest` remains the dispatcher’s fourth argument. Inside the handler, build
`Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest::fromSymfonyRequest($request, $params, $query)` when passing a read-only HTTP view into application code—do not thread Symfony types deeper than the controller layer.

Controllers should guard optional services explicitly and return `bootError` props when required service wiring is missing. After a null guard, assign dependencies to locals (e.g. `$searchService = $this->searchService`) so downstream calls are clearly non-null for readers and static analysis.

### 2.4 Lifecycle drift guard

`scripts/check-lifecycle-drift.sh` enforces that edits under watched paths (including `src/Http/Controller/`, `src/Pipeline/`, and the script itself) either update `docs/architecture/lifecycle.md` or are paired with an explicit doc note. The script uses `grep` when `rg` is unavailable so CI does not require ripgrep.

## 3. Data Lifecycle

### 3.1 Entity Registration

Entity types are declared in `AppServiceProvider::register()` and attached to `EntityTypeManager`.

### 3.2 Repository Access

App repositories (community, knowledge items) wrap `Waaseyaa\EntityStorage\EntityRepository` with `SqlStorageDriver` on the app database connection:

- load entities
- filter by community/slug
- save/delete with timestamp conventions; `KnowledgeItemRepository` optionally triggers `SearchIndexerInterface` (FTS) on save

### 3.2.1 Framework pin (Waaseyaa alpha.120+)

Giiken requires **`waaseyaa/*` ^0.1.0-alpha.120** and `nesbot/carbon` so datetime fields can use the framework’s `datetime_immutable` cast with `domain: carbon_immutable`. Default **storage** shape for that cast (no explicit `storage: unix`) is **ISO-8601 strings** (`DateTimeInterface::ATOM`). Repositories set `updated_at` with `CarbonImmutable::now()->toIso8601String()` so values round-trip through casts and `EntityRepository::save()`.

### 3.2.2 `Community` entity

- **Hydration:** `Community` implements `HydratableFromStorageInterface`. Rows are rebuilt with `Community::fromStorage()` / `Community::make()`; do not hand-roll `new Community(...)` from storage rows.
- **Constructor bag merge:** The domain constructor spreads `$extra` first, then overlays normalized `name`, `slug`, `locale`, `sovereignty_profile`, and timestamps so an import bag cannot overwrite coerced sovereignty or parsed dates with invalid raw strings.
- **Casts:** `wiki_schema` → `array`; `created_at` / `updated_at` → `datetime_immutable` + `carbon_immutable`; `sovereignty_profile` → `SovereigntyProfile` backed enum.
- **Reads:** `sovereigntyProfile()` uses `get('sovereignty_profile')` (enum cast) and `tryFrom` fallback to `Local`. Invalid strings in an import bag are normalized before they reach storage via `make()` / constructor overlay.

### 3.2.3 `KnowledgeItem` entity

- **Hydration:** Implements `HydratableFromStorageInterface` with `fromStorage()`, `make()`, and `duplicateInstance()` delegating to `fromStorage()` + `HydrationContext` (matches `ContentEntityBase` four-argument construction and avoids `ArgumentCountError` on `duplicate()` / `with()`).
- **Constructor:** Widened to `(array $values = [], string $entityTypeId = '', array $entityKeys = [], array $fieldDefinitions = [])` and forwards to `parent::__construct`.
- **Casts:** `created_at`, `updated_at`, `compiled_at` → `datetime_immutable` + `carbon_immutable`; `knowledge_type` → `KnowledgeType`; `access_tier` → `AccessTier`; JSON-backed lists → `array`. **Sanitization** in `make`/constructor coerces unknown `access_tier` to members, drops invalid `knowledge_type` strings, replaces corrupt JSON list strings with `[]` so `array` casts do not throw on legacy rows.
- **Call sites:** Application and test code should construct instances with `KnowledgeItem::make([...])`, not `new KnowledgeItem([...])`. Use `fromStorage()` only where integration tests simulate `EntityInstantiator` / DB hydration.

### 3.2.4 `WikiLintReport` entity

- Same hydratable pattern as `KnowledgeItem`: widened constructor, `make()`, `fromStorage()`, `duplicateInstance()` via `HydrationContext`.
- **Casts:** `created_at` / `updated_at` → `datetime_immutable` + `carbon_immutable`; `findings` → `array`. Jobs and callers build rows with `WikiLintReport::make([...])`. Only fields with real SQL columns are persisted; there is no `knowledge_type` column on `wiki_lint_report` (do not put stray keys into `toArray()` for save).

### 3.2.5 `EntityRepository` + Giiken SQLite tables

- `Waaseyaa\EntityStorage\EntityRepository` with `SqlStorageDriver` writes **`$entity->toArray()` keys as table columns**. Unlike `SqlEntityStorage`, this path does **not** pack unknown keys into `_data`; migrations must declare every persisted field (including `20260410_122000` JSON list columns: `knowledge_item.allowed_roles`, `allowed_users`, `source_media_ids`, `wiki_lint_report.findings`).
- **New-row id:** after `save()` on an insert, the entity object is **not** updated with the auto-increment primary key. Callers that need the numeric id should reload (e.g. `EntityRepository::findBy(['uuid' => $uuid], limit: 1)`) or extend the save path upstream.
- **Storage-normalization:** `Community::make()`, `KnowledgeItem::make()` / `fromStorage()`, and `WikiLintReport::make()` / `fromStorage()` coerce cast fields (e.g. `wiki_schema`, JSON list casts) into the canonical shapes SQLite expects so loads do not hit `CastException` on corrupt JSON or empty datetime strings.

### 3.2.6 Integration tests

- `tests/Integration/` boots `HttpKernel` with `WAASEYAA_DB=:memory:`, runs app migrations, and asserts real repository hydration, casts, and round-trips (`ContentEntitySqlIntegrationTest`, `GiikenKernelIntegrationTestCase`). Composer **`autoload-dev`** maps `App\Tests\` → `tests/` for PHPUnit.
- `ContentEntitySqlIntegrationTest` also covers `EntityInstantiator` re-hydration for all three entity types, SSR-style `<time datetime="">` formatting from ISO timestamps, `set('updated_at')` → ISO-8601 in `toArray()`, raw-SQL corrupt `wiki_lint_report.findings` normalization on load, and `toArray()` normalization after repository round-trips (enums, timestamps, JSON list columns).

### 3.3 Query + Pipeline Flow

- Discovery/search flows enter through `SearchService`.
- Q&A flows call `QaService` and then search for related items.
- Compilation flows traverse pipeline steps and persist knowledge items via repository.
- `DiscoveryController::search` pulls the user-facing search term from the `q` query-string parameter (matching the `SearchInput.vue` submit contract and `Pagination.vue` page links), constructs a `SearchQuery(query, communityId, page)`, calls `SearchService::search`, and ships `query` + `results` as Inertia props for `Pages/Discovery/Search.vue`. Empty `q` intentionally falls through to `SearchService::recentItems` so the page renders the full community feed.
- `SearchService::hybridSearch` tokenizes multi-word queries before hitting FTS to work around `Waaseyaa\Search\Fts5SearchProvider::escapeQuery`, which quotes each term and hands them to FTS5 MATCH as an implicit AND. The tokenizer is locale-aware (see waaseyaa/giiken#67): it always drops empty tokens, but the English stopword list is applied only when `SearchQuery::$locale` is null or `'en'`. Non-English locales keep every non-empty token so Indigenous-language queries are not silently eroded. The length floor is 1, not 2, since FTS5 handles single-character tokens and short stem words are meaningful across several Indigenous languages. After tokenization the service issues one FTS `SearchRequest` per surviving term and merges the per-term hits by keeping each doc's best raw score, then adds a linear "matched-more-terms-wins" bonus (`SearchService::MULTI_TERM_MATCH_BONUS * (distinct_terms_matched - 1)`) before min-max normalization so documents hit by more of the query outrank documents hit by fewer with a comparable per-term score (see waaseyaa/giiken#68). Queries that tokenize to zero terms (pure stopwords under an English locale) fall back to a single-shot pass of the original string so the vendor escaper sees exactly what it would have seen pre-tokenization. `DiscoveryController::search` and `::ask` both pass `$community->locale()` into the `SearchQuery` so the tokenizer knows which path to take. The shared prelude (build `InboundHttpRequest`, pull `communitySlug` + `q`, look up the community) lives in the private `DiscoveryController::resolveCommunityContext` helper so both methods lead with a single line (waaseyaa/giiken#71, behavior-neutral).
- `DiscoveryController::ask` reads the user's question from the same `q` query-string parameter (`SearchInput.vue` routes long or `?`-ending input to `/{slug}/ask` with key `q`), hands it to `QaServiceInterface::ask`, then calls `SearchService::search` with the question as the search term to build a related-items sidebar. The controller ships `question` (the original `q` value), `answer`, `citations` (each with `itemId`, `title`, `excerpt`, `knowledgeType`), `noRelevantItems`, and `relatedItems` as Inertia props for `Pages/Discovery/Ask.vue`. Ask.vue hands `answer` + `citations` + `noRelevantItems` to `Components/AnswerPanel.vue`, which parses `[N]` markers into anchored `<sup>` elements pointing at matching `#citation-N` cards rendered by `Components/CitationCard.vue`. When both `answer` is empty and `citations` is empty, or when `noRelevantItems` is true, `Components/NoAnswerState.vue` is rendered instead. Related items still render below via the existing `KnowledgeCard`.
- `ManagementController::ingestUpload` (`POST /{communitySlug}/manage/ingestion`) handles multipart file uploads from `Pages/Management/Ingestion.vue`. The controller reads `$httpRequest->files->get('file')` as a Symfony `UploadedFile`, then calls `IngestionHandlerRegistry::handle()` with the file's pathname, MIME type, original filename, and the resolved community. The registry dispatches to the first registered handler whose `supports($mime)` returns true. Five handlers are wired in `AppServiceProvider::registerIngestionHandlers`: `MarkdownIngestionHandler`, `CsvIngestionHandler`, `HtmlIngestionHandler`, `DocumentIngestionHandler`, and `MediaIngestionHandler`. All five depend on a single `FileRepositoryInterface` binding (`Waaseyaa\Media\LocalFileRepository` rooted at `storage/media/`); the CSV/HTML/Document handlers also depend on `FileConverterInterface` (`MarkItDownConverter` wrapping `storage/markitdown-venv/bin/markitdown`); the Media handler additionally depends on `QueueInterface` (`Waaseyaa\Queue\SyncQueue`) so audio/video uploads enqueue a no-op `TranscribeJob` placeholder. On success the controller ships a `uploadResult` Inertia prop (original filename, MIME, media id, metadata); on failure (missing file, no matching handler, handler-level `IngestionException`) it ships `uploadError` instead. See waaseyaa/giiken#39.
- `KnowledgeItemRepository::save` works around two framework quirks to keep FTS in sync: (1) after `Waaseyaa\EntityRepository::save` dispatches `POST_SAVE`, `SearchIndexSubscriber` indexes the entity while it still has a null auto-increment id, producing a stale `document_id` of `knowledge_item:`; and (2) `SearchService::hybridSearch` needs the real integer id to look items up. The repository reloads the new entity by uuid, calls `Fts5SearchIndexer::remove('knowledge_item:')` to scrub the stale empty-id row, then re-indexes the reloaded copy. `SearchService::hybridSearch` also casts the `array_keys($scores)` id back to string before calling `$this->repository->find()`, since PHP coerces numeric-string array keys to int. The `remove('knowledge_item:')` scrub assumes a single writer — in a concurrent setup worker A could scrub the empty-suffix row worker B just wrote before B re-indexes. Giiken is single-process SQLite today so this is latent, and the whole workaround goes away when the framework back-fills auto-increment ids (waaseyaa/giiken#57, tracking comment at waaseyaa/giiken#72).

## 4. Failure Lifecycle

### 4.1 Boot-time failures

- `HttpKernel::handle()` catches boot exceptions.
- If `waaseyaa/error-handler` is available and debug is true, dev exception renderer can produce HTML.
- Otherwise a JSON API error response is returned.

### 4.2 Request-time failures

- Unhandled request exceptions are logged and converted to JSON API 500 responses by kernel fallback.
- If controllers are invoked through SSR and return invalid dispatch signatures or unresolved dependencies, runtime errors surface as 500s.

### 4.3 Config-time failures

`public/index.php` catches dotenv format/path errors before kernel boot and returns a plain HTTP 500 message.

## 5. Extension Points

Primary extension points for app work:

- `src/AppServiceProvider.php`
  - service registration
  - entity type registration
  - route registration
- `src/Http/Controller/*`
  - route handlers and UI response props
- `src/Entity/*`, `src/Query/*`, `src/Pipeline/*`, `src/Export/*`
  - domain behavior and cross-cutting flows

## 6. Refactor Invariants

Keep these true during refactoring:

1. `public/index.php` always sends the response (`$response->send()`).
2. Controllers keep the active SSR dispatch signature (`array $params`, `array $query`, `AccountInterface`, `HttpRequest`) and return `Response`.
3. Optional service dependencies are handled with explicit guard returns (no implicit null behavior).
4. `AppServiceProvider` remains the single source of app route/entity registration.
5. Boot-time failures remain deterministic and observable (log + stable error response path).

## 7. Refactor Impact Matrix

| Area | Likely Impact | Verify With |
|---|---|---|
| `public/index.php` | global boot and response emission | smoke test `/`, non-zero body |
| `src/AppServiceProvider.php` | routes, entity types, DI bindings, CLI commands | route smoke tests + boot + `waaseyaa list` / migrate + seed |
| `migrations/*.php` | SQLite schema for app entities | `bin/waaseyaa migrate` + repository integration |
| `src/Http/Controller/*` | SSR dispatch and Inertia props | unit tests + route smoke tests |
| `src/Entity/*` and repositories | data shape, persistence behavior | unit tests + integration tests |
| `src/Query/*`, `src/Pipeline/*` | search/qa/compile behavior | unit tests for services and steps |

## 8. Minimal Verification Checklist

After lifecycle-touching changes:

1. `./vendor/bin/phpunit --testsuite Unit`
2. `./vendor/bin/phpstan analyse src/`
3. Start local server and verify:
   - `/` returns 200 with non-zero body
   - `/{communitySlug}` does not regress from known behavior

