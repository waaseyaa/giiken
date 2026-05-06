# HTTP Contracts — Upgrade Waaseyaa to alpha.173

**Mission:** `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Date:** 2026-05-06

## Status

**No new HTTP contracts introduced. All 18 existing routes are preserved with identical methods, paths, requirements, authentication policies, and request/response shapes.**

What changes is the **internal parameter binding** of 19 controller methods, migrated from the legacy `($params, $query, $account, $httpRequest)` signature to typed parameter injection per alpha.162. The change is invisible to clients.

## Preserved routes (unchanged in scope)

All routes registered by `App\Provider\RoutesProvider`. Route names, paths, methods, and middleware are preserved.

| Route name | Method | Path | Auth | Render |
|---|---|---|---|---|
| `giiken.home` | GET | `/` | allowAll | render |
| `giiken.login` | GET | `/login` | allowAll | render |
| `giiken.login.submit` | POST | `/login` | allowAll | render |
| `giiken.logout` | POST | `/logout` | allowAll | render |
| `giiken.discovery.index` | GET | `/{communitySlug}` | allowAll | render |
| `giiken.discovery.search` | GET | `/{communitySlug}/search` | allowAll | render |
| `giiken.discovery.ask` | GET | `/{communitySlug}/ask` | allowAll | render |
| `giiken.discovery.show` | GET | `/{communitySlug}/item/{itemId}` | allowAll | render |
| `giiken.api.v1.ask` | POST | `/api/v1/ask` | allowAll, csrfExempt, jsonApi | (api) |
| `giiken.api.v1.report` | POST | `/api/v1/report` | requireAuthentication, csrfExempt, jsonApi | (api) |
| `giiken.api.v1.synthesis` | POST | `/api/v1/synthesis` | requireAuthentication, csrfExempt, jsonApi | (api) |
| `giiken.management.dashboard` | GET | `/{communitySlug}/manage` | requireAuthentication | render |
| `giiken.management.reports` | GET | `/{communitySlug}/manage/reports` | requireAuthentication | render |
| `giiken.management.users` | GET | `/{communitySlug}/manage/users` | requireAuthentication | render |
| `giiken.management.ingestion` | GET | `/{communitySlug}/manage/ingestion` | requireAuthentication | render |
| `giiken.management.ingestion.upload` | POST | `/{communitySlug}/manage/ingestion` | requireAuthentication | render |
| `giiken.management.export.download` | GET | `/{communitySlug}/manage/export/download` | requireAuthentication | (download) |
| `giiken.management.export` | GET | `/{communitySlug}/manage/export` | requireAuthentication | render |

## Controller method signatures changing internally (Phase 4)

The 19 method signatures listed below change from generic `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` to typed parameter injection. **Their HTTP behavior is preserved.**

| Controller | Methods | Method count |
|---|---|---:|
| `DiscoveryController` | `index`, `search`, `ask`, `show`, plus one private helper bound to a route | 5 |
| `ManagementController` | `dashboard`, `reports`, `users`, `ingestion`, `ingestUpload`, `exportDownload`, `exportPage` | 7 |
| `QueryApiController` | `ask`, `report`, `saveSynthesis` | 3 |
| `WebLoginController` | `showForm`, `submit` | 2 |
| `WebLogoutController` | `logout` | 1 |
| `HomeController` | `discover` | 1 |
| **Total** | | **19** |

### Migration recipe (per method)

**Before:**

```php
public function index(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
{
    $communitySlug = $params['communitySlug'];
    $q = $query['q'] ?? null;
    // ...
}
```

**After:**

```php
public function index(
    string $communitySlug,
    #[MapQuery] ?string $q,
    AccountInterface $account,
    HttpRequest $httpRequest,
): Response {
    // ...
}
```

For methods with no route parameters and no query parameters (e.g., `WebLogoutController::logout`), the migration drops `array $params, array $query` entirely:

```php
public function logout(AccountInterface $account, HttpRequest $httpRequest): Response
```

The framework's `AppParameterBindingBuilder` resolves `$communitySlug` from the route pattern automatically (route bound via `RouteBuilder::create('/{communitySlug}/...')`).

## Verification

The 18-route HTTP surface is verified in Phase 6 by:

1. Boot-to-browser smoke path: `curl /` returns 200 Inertia "Discover", `curl /test-community` returns 200 Inertia "Discovery/Index" with seeded items.
2. Existing PHPUnit controller tests under `tests/Unit/Http/Controller/` and `tests/Integration/Http/`.
3. Deprecation notice capture: zero `implicit_array_unbound` and zero `(controller_class, method, parameter_name)` shim entries fired by `AppParameterBindingBuilder`'s structured `LoggerInterface::notice` (alpha.173 shim).
