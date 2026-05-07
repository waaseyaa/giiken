# Migrations resolved — implicit-array shim mission

**Mission:** `eliminate-implicit-array-shim-01KQZX6V`  
**Implementation commit:** `030f9ec` on `main` (2026-05-07)

## Controller inventory (SC-3 / migration-notes)

Every SSR dispatch entry method that previously used unannotated `array $params` and `array $query` now carries `#[MapRoute]` and `#[MapQuery]` (`Waaseyaa\SSR\Attribute\*`):

| Controller | Methods |
|------------|---------|
| `HomeController` | `discover` |
| `WebLoginController` | `showForm`, `submit` |
| `WebLogoutController` | `logout` |
| `DiscoveryController` | `index`, `search`, `ask`, `show` |
| `ManagementController` | `dashboard`, `reports`, `users`, `ingestion`, `ingestUpload`, `exportPage`, `exportDownload` |
| `QueryApiController` | `ask`, `report`, `saveSynthesis` |

Private helpers that accept `$params` / `$query` as ordinary PHP parameters (e.g. `DiscoveryController::resolveCommunityContext`) are **not** dispatcher entry points and remain unannotated by design.

## Entity inventory

| Entity | Change |
|--------|--------|
| `Community` | `#[ContentEntityType]` + `#[ContentEntityKeys]` |
| `KnowledgeItem` | Same + removed fourth `fieldDefinitions` constructor argument |
| `WikiLintReport` | Same + removed fourth `fieldDefinitions` constructor argument |
| `EntitiesProvider` | `EntityType::fromClass()` for all three types |

## FR-003 (implicit_array_unbound)

No additional unannotated controller `array` parameters were in scope for this mission; none required ad-hoc binding beyond the params/query pair.
