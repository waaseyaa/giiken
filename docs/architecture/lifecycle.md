# Giiken Application Lifecycle

This document describes how a request moves through Giiken at runtime, where app-level code hooks into Waaseyaa, and what invariants should remain true during refactoring.

## Scope

- App: `giiken`
- Framework: `waaseyaa/*`
- Entrypoint: `public/index.php`
- Primary app integration point: `src/GiikenServiceProvider.php`

## 1. Boot Lifecycle

### 1.1 Entrypoint

`public/index.php`:

1. Loads Composer autoloader.
2. Loads `.env` with `Symfony Dotenv` (fails fast with HTTP 500 on parse/path error).
3. Instantiates `Waaseyaa\Foundation\Kernel\HttpKernel` with project root.
4. Calls `$kernel->handle()` and then `$response->send()`.

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

The first Giiken app-level class in normal boot is `Giiken\GiikenServiceProvider`:

- `register()` contributes app entity types (`community`, `knowledge_item`, `wiki_lint_report`)
- `register()` binds app services resolved by SSR `serviceResolver`: `CommunityRepositoryInterface`, `KnowledgeItemRepositoryInterface`, `SearchService`, `QaServiceInterface`, dev `NullEmbeddingProvider` / `NullLlmProvider`, and a PSR-14 `EventDispatcherInterface` alias to the kernel dispatcher (for `EntityRepository` construction); registers `Giiken\Http\Inertia\InertiaHttpResponder` (full-page renderer from DI when present)
- `commands()` contributes CLI commands (`giiken:seed:test-community`)
- `routes()` contributes app HTTP routes (discovery, management, `GET`/`POST` `/login`, `GET` `/logout`)

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

App routes are added through `GiikenServiceProvider::routes(...)`, including:

- Session HTML auth (public): `GET`/`POST` `/login`, `GET` `/logout`
- Discovery:
  - `/{communitySlug}`
  - `/{communitySlug}/search`
  - `/{communitySlug}/ask`
  - `/{communitySlug}/item/{itemId}`
- Management (`_authenticated`):
  - `/{communitySlug}/manage`
  - `/{communitySlug}/manage/reports`
  - `/{communitySlug}/manage/users`
  - `/{communitySlug}/manage/ingestion`
  - `/{communitySlug}/manage/export`

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

Entity types are declared in `GiikenServiceProvider::register()` and attached to `EntityTypeManager`.

### 3.2 Repository Access

App repositories (community, knowledge items) wrap `Waaseyaa\EntityStorage\EntityRepository` with `SqlStorageDriver` on the app database connection:

- load entities
- filter by community/slug
- save/delete with timestamp conventions; `KnowledgeItemRepository` optionally triggers `SearchIndexerInterface` (FTS) on save

### 3.3 Query + Pipeline Flow

- Discovery/search flows enter through `SearchService`.
- Q&A flows call `QaService` and then search for related items.
- Compilation flows traverse pipeline steps and persist knowledge items via repository.

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

- `src/GiikenServiceProvider.php`
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
4. `GiikenServiceProvider` remains the single source of app route/entity registration.
5. Boot-time failures remain deterministic and observable (log + stable error response path).

## 7. Refactor Impact Matrix

| Area | Likely Impact | Verify With |
|---|---|---|
| `public/index.php` | global boot and response emission | smoke test `/`, non-zero body |
| `src/GiikenServiceProvider.php` | routes, entity types, DI bindings, CLI commands | route smoke tests + boot + `waaseyaa list` / migrate + seed |
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

