# Giiken Application Lifecycle

This document describes how a request moves through Giiken at runtime, where app-level code hooks into Waaseyaa, and what invariants should remain true during refactoring.

## Scope

- App: `giiken`
- Framework: `waaseyaa/*`
- Entrypoint: `public/index.php`
- Primary app integration points: `src/Provider/*.php` (with `AppServiceProvider` still carrying residual boot + console responsibilities)

## 1. Boot Lifecycle

### 1.1 Entrypoint

`public/index.php`:

1. **CLI-server static file guard:** When running under PHP's built-in server (`PHP_SAPI === 'cli-server'`), checks if the request maps to an existing file on disk and returns `false` to let the server serve it directly. No effect on production servers.
2. Loads Composer autoloader.
3. Instantiates `Waaseyaa\Foundation\Kernel\HttpKernel` with project root (`dirname(__DIR__)`).
4. Calls `$kernel->handle()->send()`.

`.env` loading is owned by the kernel (`AbstractKernel::boot()` invokes `EnvLoader::load()`), not the entry point — see §1.2 step 1.

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

The first Giiken app-level classes in normal boot are the providers listed in `composer.json > extra.waaseyaa.providers`. `App\Provider\AppServiceProvider` still owns residual boot + command wiring, while specialized providers now own focused concerns such as entities, authz, routes, frontend bootstrapping, query wiring, and ingestion bootstrapping. A concise map of the three persisted aggregates (community, knowledge item, wiki lint report), their relationships, and pointers to provenance and RBAC lives in [domain-model.md](./domain-model.md).

- `App\Provider\EntitiesProvider::register()` registers the three app entity types via `EntityType::fromClass()` (class-level `#[ContentEntityType]` + `#[ContentEntityKeys]` on `Community`, `KnowledgeItem`, `WikiLintReport`) and binds the app repositories for `CommunityRepositoryInterface` / `KnowledgeItemRepositoryInterface`
- `register()` binds app services resolved by SSR `serviceResolver`: `CommunityRepositoryInterface`, `KnowledgeItemRepositoryInterface`, `SearchService`, `QaServiceInterface`, `ReportServiceInterface`, `ExportServiceInterface`, `SynthesisService`, `NullEmbeddingProvider`; `LlmProviderInterface` is wired conditionally — if `WAASEYAA_LLM_PROVIDER=anthropic` and `ANTHROPIC_API_KEY` are set at boot time, the singleton resolves to `AnthropicLlmProvider` (wrapping `Waaseyaa\AI\Agent\Provider\AnthropicProvider`), otherwise falls back to `NullLlmProvider`; and a PSR-14 `EventDispatcherInterface` alias to the kernel dispatcher (for `EntityRepository` construction); registers `App\Http\Inertia\InertiaHttpResponder` (full-page renderer from DI when present)
- `App\Provider\FrontendProvider::register()` binds `InertiaHttpResponder`, re-binds `InertiaFullPageRendererInterface` with a project-root-based `ViteAssetManager` (`public/build` manifest or `VITE_DEV_SERVER`), sets `Inertia::setVersion('giiken')`, and refreshes `Inertia::setRenderer(...)` with a custom template closure that rewrites the data-page attribute from `data-page="true"` to `data-page="app"` so Inertia v2's client-side reader (`script[data-page="app"]`) actually finds the initial page object — workaround for waaseyaa/framework#1227. The same closure injects `<meta name="csrf-token" ...>` from `CsrfMiddleware::token()` so the browser can attach `X-CSRF-Token` on state-changing Inertia visits (required for multipart ingestion uploads). `resources/js/app.ts` sets `defaults.visitOptions` to merge that header on every visit. The project root is computed as `dirname(__DIR__, 2)` from `src/Provider/`; getting that wrong (e.g. `dirname(__DIR__)`) silently disables asset emission, producing a blank `<head>` and an empty `#app` on every route — regression-guarded by `tests/Integration/Http/RootTemplateAssetsTest.php` (giiken#90).
- `App\Provider\AuthzProvider::register()` binds the shared `CommunityRoleResolverInterface` and `KnowledgeItemAccessPolicy`, so community-role parsing and item access checks boot independently from repository/query wiring.
- `App\Provider\QueryProvider::register()` now owns the AI/query-side bindings: embedding + LLM providers, ask-request validation and rate limiting, `SearchService`, `QaServiceInterface`, `ReportServiceInterface`, `ExportServiceInterface`, `SynthesisService`, and `CompilationPipeline`.
- `App\Provider\IngestionProvider::register()` now owns file/media ingestion bootstrapping: upload validation, local media repository, synchronous queue, MarkItDown runner + converter bindings, and the assembled `IngestionHandlerRegistry`.
- Frontend bundle: Vite entry `resources/js/app.ts`, production output under `public/build` (`npm run build`); set `VITE_DEV_SERVER` (e.g. `http://127.0.0.1:5173`) when using `npm run dev` for HMR. `vite.config.ts` now sets `publicDir: false` explicitly so Vite does not try to copy `public/` into `public/build`, eliminating the overlapping-directory warning while keeping the manifest path unchanged for `FrontendProvider`.
- `register()` also binds `CompilationPipeline` as a singleton (built from the configured `LlmProviderInterface`, `EmbeddingProviderInterface`, and a raw `knowledge_item` `WaaseyaaEntityRepository`) so CLI and future HTTP ingestion surfaces share a single pipeline instance
- `App\Provider\AppServiceProvider` implements `HasNativeCommandsInterface` and contributes native CLI commands **`giiken:seed:test-community`** and **`giiken:ingest:file`** via `nativeCommands()` (`CommandDefinition` + `GiikenSeedTestCommunityHandler` / `GiikenIngestFileHandler`). Global **`search:reindex`** ships with `Waaseyaa\CLI` — Giiken does not register a duplicate (waaseyaa native-cli-kernel; see giiken#94 legacy note)
- `App\Provider\RoutesProvider::routes()` contributes app HTTP routes (discovery, management, `GET`/`POST` `/login`, `POST` `/logout`)
- `HomeController::discover` (`GET /`) injects `CommunityRepositoryInterface` and ships the result of `findAll()` as the `communities` Inertia prop for `Pages/Discover.vue`, which renders a community card grid linking into `/{slug}` Discovery pages

### 1.4 Schema and local data

- App SQL migrations live in `migrations/` and run via `./vendor/bin/waaseyaa migrate` (or app bootstrap) when pending.
- Tables `community`, `knowledge_item`, and `wiki_lint_report` must exist before repository saves; optional demo data: `./vendor/bin/waaseyaa giiken:seed:test-community` after migrate (ensures `test-community`, demo `giiken_staff` with password `giiken-dev` or `GIIKEN_SEED_STAFF_PASSWORD`, and that user’s staff role for that community when `EntityTypeManager` is available).

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

App routes are added through `RoutesProvider::routes(...)`, including:

- Public landing (Inertia): `GET` `/` → `Discover` page (`HomeController::discover`)
- Session HTML auth (public): `GET`/`POST` `/login`, `POST` `/logout`
- Discovery:
  - `/{communitySlug}`
  - `/{communitySlug}/search`
  - `/{communitySlug}/ask`
  - `/{communitySlug}/item/{itemId}`
- Query API (JSON, CSRF-exempt; `POST` bodies are JSON):
  - `POST /api/v1/ask` (`_public`) — Q&A + structured citations, validated `communitySlug`/`question`, per-community+IP rate limited
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

SSR app controllers use the four-argument dispatch shape; they return **`Symfony\Component\HttpFoundation\Response`**, not raw `InertiaResponse`, so `SsrPageHandler` can emit HTML or JSON. Internally they call `Inertia::render(...)` and pass the result through `InertiaHttpResponder::toResponse()`. Route and query-string bags use explicit `Waaseyaa\SSR\Attribute\MapRoute` / `MapQuery` so dispatch does not rely on the framework’s implicit-array compatibility shim.

```php
public function action(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
```

Symfony `HttpRequest` remains the dispatcher’s fourth argument. Inside the handler, build
`Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest::fromSymfonyRequest($request, $params, $query)` when passing a read-only HTTP view into application code—do not thread Symfony types deeper than the controller layer.

Controllers should guard optional services explicitly and return `bootError` props when required service wiring is missing. After a null guard, assign dependencies to locals (e.g. `$searchService = $this->searchService`) so downstream calls are clearly non-null for readers and static analysis.

### 2.4 Lifecycle drift guard

`scripts/check-lifecycle-drift.sh` enforces that edits under watched paths (including `src/Http/Controller/`, `src/Pipeline/`, and the script itself) either update `docs/architecture/lifecycle.md` or are paired with an explicit doc note. The script uses `grep` when `rg` is unavailable so CI does not require ripgrep.

## 3. Data Lifecycle

### 3.1 Entity Registration

Entity types are declared in `EntitiesProvider::register()` and attached to `EntityTypeManager`.

### 3.2 Repository Access

App repositories (community, knowledge items) wrap `Waaseyaa\EntityStorage\EntityRepository` with `SqlStorageDriver` on the app database connection:

- load entities
- filter by community/slug
- save/delete with timestamp conventions; FTS is kept in sync by the framework's `SearchIndexSubscriber` listening on `POST_SAVE`

### 3.2.1 Framework pin (Waaseyaa alpha.144+)

Giiken requires **`waaseyaa/*` ^0.1.0-alpha.144** and `nesbot/carbon` so datetime fields can use the framework’s `datetime_immutable` cast with `domain: carbon_immutable`. Default **storage** shape for that cast (no explicit `storage: unix`) is **ISO-8601 strings** (`DateTimeInterface::ATOM`). Repositories set `updated_at` with `CarbonImmutable::now()->toIso8601String()` so values round-trip through casts and `EntityRepository::save()`.

### 3.2.2 `Community` entity

- **Registration metadata:** The class declares `#[ContentEntityType(id: 'community', …)]` and `#[ContentEntityKeys]` so `EntityType::fromClass(Community::class)` matches the historical `EntityType` keys (`id` / `uuid` / `name` label column).
- **Hydration:** `Community` implements `HydratableFromStorageInterface`. Rows are rebuilt with `Community::fromStorage()` / `Community::make()`; do not hand-roll `new Community(...)` from storage rows.
- **Constructor bag merge:** The domain constructor spreads `$extra` first, then overlays normalized `name`, `slug`, `locale`, `sovereignty_profile`, and timestamps so an import bag cannot overwrite coerced sovereignty or parsed dates with invalid raw strings.
- **Casts:** `wiki_schema` → `array`; `created_at` / `updated_at` → `datetime_immutable` + `carbon_immutable`; `sovereignty_profile` → `SovereigntyProfile` backed enum.
- **Slug invariant:** `community.slug` is now unique at both the repository layer and the database layer (`community_slug_unique`). `CommunityRepository::save()` rejects duplicate slugs before persistence, and the migration refuses to add the index if preexisting duplicates are present.
- **Reads:** `sovereigntyProfile()` uses `get('sovereignty_profile')` (enum cast) and `tryFrom` fallback to `Local`. Invalid strings in an import bag are normalized before they reach storage via `make()` / constructor overlay.

### 3.2.3 `KnowledgeItem` entity

- **Registration metadata:** `#[ContentEntityType(id: 'knowledge_item', …)]` and `#[ContentEntityKeys]` align with `EntityType::fromClass(KnowledgeItem::class)`.
- **Hydration:** Implements `HydratableFromStorageInterface` with `fromStorage()`, `make()`, and `duplicateInstance()` delegating to `fromStorage()` + `HydrationContext` (matches `ContentEntityBase` three-argument construction and avoids `ArgumentCountError` on `duplicate()` / `with()`).
- **Constructor:** `(array $values = [], string $entityTypeId = '', array $entityKeys = [])` forwards to `parent::__construct($values, $entityTypeId, $entityKeys)` (no legacy `fieldDefinitions` bag).
- **Casts:** `created_at`, `updated_at`, `compiled_at` → `datetime_immutable` + `carbon_immutable`; `knowledge_type` → `KnowledgeType`; `access_tier` → `AccessTier`; JSON-backed lists → `array`. **Sanitization** in `make`/constructor coerces unknown `access_tier` to members, drops invalid `knowledge_type` strings, replaces corrupt JSON list strings with `[]` so `array` casts do not throw on legacy rows.
- **Call sites:** Application and test code should construct instances with `KnowledgeItem::make([...])`, not `new KnowledgeItem([...])`. Use `fromStorage()` only where integration tests simulate `EntityInstantiator` / DB hydration.

### 3.2.4 `WikiLintReport` entity

- **Registration metadata:** `#[ContentEntityType(id: 'wiki_lint_report', …)]` and `#[ContentEntityKeys]` for `EntityType::fromClass(WikiLintReport::class)`.
- Same hydratable pattern as `KnowledgeItem`: `make()`, `fromStorage()`, `duplicateInstance()` via `HydrationContext`; constructor forwards three arguments to `ContentEntityBase`.
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
- Compilation flows traverse pipeline steps and persist knowledge items via repository. `CompilationPipeline::compile()` returns the populated `CompilationPayload` (populated `entityUuid`, `knowledgeType`, `accessTier`) so callers — `App\Console\IngestFileCommand::run()` for the **`giiken:ingest:file`** Waaseyaa native CLI command (`GiikenIngestFileHandler`), the Management UI tomorrow — can surface the persisted id without a follow-up query (waaseyaa/giiken#95). Ingest orchestration and MIME detection live in **`IngestFileCommand`** with zero Symfony imports; registration is **`HasNativeCommandsInterface::nativeCommands()`** (`Waaseyaa\CLI\CliKernel`). Optional `$accessTier`, `$forcedType`, and `$dryRun` parameters let operators override persisted access tier, skip `ClassifyStep`'s LLM round-trip, and run all steps without touching the repository or embedding store.
- `DiscoveryController::search` pulls the user-facing search term from the `q` query-string parameter (matching the now-explicit `SearchInput.vue` submit contract and `Pagination.vue` page links), constructs a `SearchQuery(query, communityId, page)`, calls `SearchService::search`, and ships `query` + `results` as Inertia props for `Pages/Discovery/Search.vue`. `SearchInput` no longer guesses between search and ask modes: callers pass `mode="search"` or `mode="ask"`, and the component defaults to search when omitted. Empty `q` intentionally falls through to `SearchService::recentItems` so the page renders the full community feed.
- `SearchService::hybridSearch` tokenizes multi-word queries before hitting FTS to work around `Waaseyaa\Search\Fts5SearchProvider::escapeQuery`, which quotes each term and hands them to FTS5 MATCH as an implicit AND. The tokenizer is locale-aware (see waaseyaa/giiken#67): it always drops empty tokens, but the English stopword list is applied only when `SearchQuery::$locale` is null or `'en'`. Non-English locales keep every non-empty token so Indigenous-language queries are not silently eroded. The length floor is 1, not 2, since FTS5 handles single-character tokens and short stem words are meaningful across several Indigenous languages. After tokenization the service issues one FTS `SearchRequest` per surviving term and merges the per-term hits by keeping each doc's best raw score, then adds a linear "matched-more-terms-wins" bonus (`SearchService::MULTI_TERM_MATCH_BONUS * (distinct_terms_matched - 1)`) before min-max normalization so documents hit by more of the query outrank documents hit by fewer with a comparable per-term score (see waaseyaa/giiken#68). Queries that tokenize to zero terms (pure stopwords under an English locale) fall back to a single-shot pass of the original string so the vendor escaper sees exactly what it would have seen pre-tokenization. `DiscoveryController::search` and `::ask` both pass `$community->locale()` into the `SearchQuery` so the tokenizer knows which path to take. The shared prelude (build `InboundHttpRequest`, pull `communitySlug` + `q`, look up the community) lives in the private `DiscoveryController::resolveCommunityContext` helper so both methods lead with a single line (waaseyaa/giiken#71, behavior-neutral).
- `DiscoveryController::ask` reads the user's question from the same `q` query-string parameter (`SearchInput.vue` routes long or `?`-ending input to `/{slug}/ask` with key `q`), hands it to `QaServiceInterface::ask`, then calls `SearchService::search` with the question as the search term to build a related-items sidebar. The controller ships `question` (the original `q` value), `answer`, `citations` (each with `itemId`, `title`, `excerpt`, `knowledgeType`), `noRelevantItems`, and `relatedItems` as Inertia props for `Pages/Discovery/Ask.vue`. Ask.vue hands `answer` + `citations` + `noRelevantItems` to `Components/AnswerPanel.vue`, which parses `[N]` markers into anchored `<sup>` elements pointing at matching `#citation-N` cards rendered by `Components/CitationCard.vue`. When both `answer` is empty and `citations` is empty, or when `noRelevantItems` is true, `Components/NoAnswerState.vue` is rendered instead. Related items still render below via the existing `KnowledgeCard`.
- `QueryApiController::ask` now validates JSON into an ask-request DTO before dispatch: `communitySlug` and `question` are required, leading/trailing whitespace is trimmed, and `question` is capped by `config['api']['ask']['question_max_length']`. Before invoking `QaServiceInterface`, the controller consumes a file-backed rate-limit bucket keyed by `communitySlug + REMOTE_ADDR`, using `storage/framework/rate-limits/ask/` with `api.ask.rate_limit.max_attempts` / `window_seconds`. Exceeded buckets return `429` JSON with `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining: 0`.
- `DiscoveryController::show` resolves the community by slug first, then loads the item through `KnowledgeItemRepositoryInterface::findByCommunityAndId(communityId, itemId)` instead of a raw global `find(id)` call. That keeps `/{communitySlug}/item/{itemId}` scoped to the requested tenant and closes cross-community IDOR leaks. After the scoped lookup, the controller runs `KnowledgeItemAccessPolicy::access(..., 'view', $account)` and renders `item: null` when the account is not allowed to view the row, so the page contract stays stable while unauthorized or out-of-scope ids do not disclose record contents.
- `ManagementController` now applies a shared community-role resolver before rendering any management surface or executing upload/export actions. Every `/{communitySlug}/manage*` endpoint still relies on route-level authentication, but controller dispatch now also requires a role of `staff` or above in the resolved community and returns `403 Forbidden` when an authenticated account lacks that tenant-scoped role. The same resolver also backs `KnowledgeItemAccessPolicy` and `ReportService`, so community-role parsing has one runtime source of truth.
- `ManagementController::ingestUpload` (`POST /{communitySlug}/manage/ingestion`) handles multipart file uploads from `Pages/Management/Ingestion.vue`. The controller reads `$httpRequest->files->get('file')` as a Symfony `UploadedFile`, then hands it to `UploadValidatorInterface` before any handler runs. The default `UploadedFileValidator` enforces `upload_max_bytes`, sniffs MIME via `finfo`, normalizes a small set of ambiguous extension-based cases (`.md`, `.csv`, OOXML, etc.), and rejects files outside `upload_allowed_mime_types`; it never trusts `getClientMimeType()`. Only the returned `ValidatedUpload` (path, original filename, detected MIME, size) is passed to `IngestionHandlerRegistry::handle()`. The registry dispatches to the first registered handler whose `supports($mime)` returns true. Five handlers are wired in `AppServiceProvider::registerIngestionHandlers`: `MarkdownIngestionHandler`, `CsvIngestionHandler`, `HtmlIngestionHandler`, `DocumentIngestionHandler`, and `MediaIngestionHandler`. All five depend on a single `FileRepositoryInterface` binding (`Waaseyaa\Media\LocalFileRepository` rooted at `storage/media/`); the CSV/HTML/Document handlers also depend on `FileConverterInterface` (`MarkItDownConverter` wrapping an explicit `ingestion.markitdown_binary` path). The converter no longer shells out with `exec()`: it delegates to `ProcOpenMarkItDownRunner`, which executes `[binary, file]` with `proc_open`, a bounded timeout (`ingestion.command_timeout_seconds`), and a stdout-only return contract. Non-zero exit codes and timeouts are surfaced as sanitized `ConversionException`s without dumping raw stderr back into controller errors. The Media handler additionally depends on `QueueInterface` (`Waaseyaa\Queue\SyncQueue`) so audio/video uploads enqueue a no-op `TranscribeJob` placeholder. On success the controller ships a `uploadResult` Inertia prop (original filename, MIME, media id, metadata); on failure (missing file, validation rejection, no matching handler, handler-level `IngestionException`) it ships `uploadError` instead. See waaseyaa/giiken#39.
- `ImportService::import` extracts ZIP archives into a temp directory under `sys_get_temp_dir()`, but extraction is no longer delegated to `ZipArchive::extractTo()`. Each entry name is normalized and rejected if it is empty, absolute, drive-qualified, NUL-containing, or contains `.` / `..` path segments; only then is the entry streamed to disk beneath the temp directory. That keeps dormant import support from allowing zip-slip writes outside the import sandbox.
- `KnowledgeItemRepository::save` sets `updated_at` and delegates to `Waaseyaa\EntityRepository::save`. The framework's `SqlStorageDriver` captures the auto-increment pk via `lastInsertId` and `EntityRepository::doSave` back-fills the entity before dispatching `POST_SAVE`, so `SearchIndexSubscriber` indexes the new row under its real `knowledge_item:N` document id with no post-save scrub or reload needed (closed by waaseyaa/giiken#57). `SearchService::hybridSearch` still casts `array_keys($scores)` back to string before calling `$this->repository->find()`, since PHP coerces numeric-string array keys to int.
- `KnowledgeItem` still exposes `toMarkdown()`, `toSearchDocument()`, and `toSearchMetadata()` for compatibility with pipeline/search call sites, but those methods now delegate to dedicated collaborators (`KnowledgeItemMarkdownPresenter`, `KnowledgeItemSearchDocumentFactory`, `KnowledgeItemSearchMetadataFactory`). That begins the entity-thinning work without changing the external API surface yet.

## 3.4 LLM Provider Adapters

`src/Pipeline/Provider/` holds thin adapters that implement `LlmProviderInterface` (`complete(string $systemPrompt, string $userPrompt): string`):

- `NullLlmProvider` — safe default for local dev; returns canned text, no network calls.
- `AnthropicLlmProvider` — wraps `Waaseyaa\AI\Agent\Provider\ProviderInterface` (injected); builds a `MessageRequest` and returns `MessageResponse::getText()`. No lifecycle behavior change; the adapter is wired by the DI container at boot time through `AppServiceProvider`.

No request path, routing, or boot sequence is affected by adding or swapping these adapters.

## 4. Failure Lifecycle

### 4.1 Boot-time failures

- `HttpKernel::handle()` catches boot exceptions.
- If `waaseyaa/error-handler` is available and debug is true, dev exception renderer can produce HTML.
- Otherwise a JSON API error response is returned.

### 4.2 Request-time failures

- Unhandled request exceptions are logged and converted to JSON API 500 responses by kernel fallback.
- If controllers are invoked through SSR and return invalid dispatch signatures or unresolved dependencies, runtime errors surface as 500s.

### 4.3 Config-time failures

`.env` parse and path errors raise inside `EnvLoader::load()` during `AbstractKernel::boot()`. `HttpKernel::handle()` catches bootstrap exceptions and emits a plain HTTP 500 response; `ConsoleKernel::handle()` renders them to stderr and exits non-zero.

## 5. Extension Points

Primary extension points for app work:

- `src/Provider/AppServiceProvider.php`
  - residual `register()` bindings (e.g. event dispatcher alias, `IngestFileCommand` + Giiken CLI handler singletons), `boot()` NorthCloud mapper registration, and `HasNativeCommandsInterface::nativeCommands()` for **`giiken:seed:test-community`** and **`giiken:ingest:file`** only
- `src/Http/Controller/*`
  - route handlers and UI response props
- `src/Entity/*`, `src/Query/*`, `src/Pipeline/*`, `src/Export/*`
  - domain behavior and cross-cutting flows

## 6. Refactor Invariants

Keep these true during refactoring:

1. `public/index.php` always sends the response (`$response->send()`).
2. Controllers keep the active SSR dispatch signature (`#[MapRoute] array $params`, `#[MapQuery] array $query`, `AccountInterface`, `HttpRequest`) and return `Response`.
3. Optional service dependencies are handled with explicit guard returns (no implicit null behavior).
4. Route registration stays centralized in `RoutesProvider`, and entity registration stays centralized in its dedicated provider rather than drifting back into mixed-responsibility providers.
5. Boot-time failures remain deterministic and observable (log + stable error response path).

## 7. Refactor Impact Matrix

| Area | Likely Impact | Verify With |
|---|---|---|
| `public/index.php` | global boot and response emission | smoke test `/`, non-zero body |
| `src/Provider/RoutesProvider.php` | HTTP route registration | route smoke tests |
| `src/Provider/FrontendProvider.php` | Inertia responder + root template/Vite asset wiring | root-template asset integration tests |
| `src/Provider/AuthzProvider.php` | community role resolver + knowledge item access policy | authz policy + management/report authz tests |
| `src/Provider/EntitiesProvider.php` | entity type registration + app repository bindings | entity integration + repository-backed route tests |
| `src/Provider/QueryProvider.php` | AI/query/pipeline bindings + ask API support seams | query + API controller tests |
| `src/Provider/IngestionProvider.php` | upload validation + converter/media/handler bindings | ingestion controller + validator + converter tests |
| `src/Provider/AppServiceProvider.php` | residual boot hooks + native CLI (`HasNativeCommandsInterface`) | boot + `./vendor/bin/waaseyaa …` / migrate + seed |
| `migrations/*.php` | SQLite schema for app entities | `./vendor/bin/waaseyaa migrate` + repository integration |
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

## 9. Change Log

### 2026-04-15 — KnowledgeItem structured source (no runtime dispatch change)

- Added `KnowledgeItemSource` value object + 4 indexed hot columns to `knowledge_item` (see `migrations/20260415_140000_add_knowledge_item_source_columns.php`).
- Added `Ingestion\NorthCloud\NcHitToKnowledgeItemMapper` as the reference mapper for NC-sourced provenance; it is registered into the package `MapperRegistry` from `AppServiceProvider::boot()` (see changelog entry below). No HTTP route or pipeline step consumes the mapper directly — `northcloud:sync` does.
- No changes to boot, routing, SSR dispatch, or request/response shape for normal web requests beyond provider boot wiring noted in the NorthCloud entries.
- Full rationale and provenance schema in `docs/architecture/knowledge-item-source.md`.

### 2026-04-15 — NorthCloud mapper wired into AppServiceProvider

- `AppServiceProvider::registerNorthCloudMappers()` overrides the package's `MapperRegistry` singleton factory, pre-populating it with `NcHitToKnowledgeItemMapper`.
- Default community id for NC-sourced items reads from env `GIIKEN_NC_DEFAULT_COMMUNITY_ID`.
- `NorthCloudServiceProvider` (auto-loaded from `extra.waaseyaa.providers`) contributes the `northcloud:sync` console command and the `NorthCloudClient`, `NcSyncService`, and `NorthCloudSearchProvider` services.
- No HTTP route or SSR dispatch change. `./vendor/bin/waaseyaa list` surfaces `northcloud:sync`; request-lifecycle behavior is unaffected.

### 2026-04-16 — NorthCloud mapper registration made fail-loud

- `AppServiceProvider::registerNorthCloudMappers()` now resolves the package-owned `MapperRegistry` during `boot()` and throws a descriptive `RuntimeException` if the registry binding is unavailable.
- Failure is additionally logged through `LoggerInterface` when available so missing package-manifest/provider wiring is visible in logs.
- Added integration coverage to assert `NcHitToKnowledgeItemMapper` is registered exactly once in the live kernel (`tests/Integration/Ingestion/NorthCloud/NorthCloudMapperRegistrationTest.php`).
- No route, controller, or SSR dispatch changes; this is startup wiring hardening only.

### 2026-04-16 — NorthCloud sync observability + strict Robinson-Huron relevance

- `northcloud:sync` now supports dry-run observability output (`--explain`, `--sample`, `--report-json`) via package-level sync diagnostics, enabling non-persistent ingest audits.
- `NcHitToKnowledgeItemMapper` now returns explicit support diagnostics (`missing_url`, `missing_indigenous_topic`, `missing_regional_signal`) and applies stricter Robinson-Huron-adjacent relevance terms.
- No HTTP route, middleware, or SSR dispatch behavior changed; this update only affects CLI sync filtering/diagnostics and associated mapper decision visibility.
