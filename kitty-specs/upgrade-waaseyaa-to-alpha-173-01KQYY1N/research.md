# Phase 0 Research: Upgrade Waaseyaa to alpha.173

**Mission:** `01KQYY1NT1BW6F7QKZA02969PB` — `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Date:** 2026-05-06
**Sources:**

- `/home/jones/dev/waaseyaa/CHANGELOG.md` lines 10–287 (alpha.145 .. alpha.173)
- Targeted grep over `/home/jones/dev/giiken/src/` for legacy patterns

---

## 1. Per-Release Decision Matrix

The upstream release sequence between alpha.145 (2026-04-16) and alpha.173 (2026-05-05) — 28 alphas over ~3 weeks — sorted into three impact tiers for Giiken.

### High-impact (breaking changes Giiken must adapt to)

| Release | Date | Change | Giiken impact |
|---|---|---|---|
| **alpha.162** | 2026-04-28 | **Attribute-first entity definition (M1).** `EntityType::fromClass()` introduced; `#[ContentEntityType]` + `#[Field]` attributes replace constructor-time `fieldDefinitions:` parameter. `EntityTypeManager::assertClassMetadataMatchesEntityType()` removed. | **Direct.** 2 entity files match `fieldDefinitions:` pattern (`KnowledgeItem.php`, `WikiLintReport.php`). Phase 3. |
| **alpha.162** | 2026-04-28 | **SSR app controllers: typed parameter injection.** Legacy `($params, $query, $account, $httpRequest)` invocation removed. Routes use `RouteBuilder::bind($name, class-string)` for entity upcasting; `EntityParamConverter` provides the typed entity. | **Direct.** 6 controllers, 19 methods match the legacy signature. Phase 4. |
| **alpha.162** | 2026-04-28 | **Backed-enum field type plugin.** `enum` field type replaces transitional `'string' + settings.enum_class` bridge. Explicit `type='string'` on a backed-enum property no longer accepted. | **Indirect.** Giiken has backed enums (`AccessTier`, `KnowledgeType`, `CommunityRole`, `OriginType`, `CopyrightStatus`) but they are not currently registered as `#[Field]`-typed; once Phase 3 adds attribute-first registration, fields backed by these enums must declare `type: 'enum'` or rely on inference. |
| **alpha.171** | 2026-05-04 | **`ServiceProvider::setKernelResolver()` removed.** Replaced by `setKernelServices(KernelServicesInterface)` plus `mergeChildProvider()`. | **None.** Giiken has zero call sites — already on the modern provider lifecycle. |
| **alpha.171** | 2026-05-04 | **`Waaseyaa\Api\JsonResponseTrait` redesigned.** Single `jsonApiResponse()` returning `application/vnd.api+json`. Previous `json()`/`jsonBody()` surface dropped. | **None.** Giiken's `QueryApiController` uses a private `jsonBody()` helper, not the framework trait. Confirmed by grep — no `JsonResponseTrait` import. |
| **alpha.173** | 2026-05-05 | **Implicit-array controller signature compatibility shim.** Restores alpha.170 binding: unannotated `array $params` defaults to `#[MapRoute]`, unannotated `array $query` defaults to `#[MapQuery]`. Each shim hit emits a structured `LoggerInterface::notice` per `(controller_class, method, parameter_name)`. | **Mitigates** the alpha.162 controller breakage. Giiken's tests will likely pass mid-Phase-4 with deprecation notices, not hard failures — but Phase 4 is not "done" until those notices fire zero times (per project doctrine). |

### Medium-impact (additive features Giiken can adopt or ignore)

| Release | Date | Change | Giiken stance |
|---|---|---|---|
| alpha.165 | 2026-05-02 | Schema evolution v2: `bin/waaseyaa migrate --dry-run` and `--verify`; nullable `checksum` + `diff_hash` columns on `waaseyaa_migrations`; `extra.waaseyaa.migrations` accepts ordered array form | Optional adoption — useful operationally; not required for the upgrade. May surface during Phase 1 baseline if migrations table reshape requires backfill (null-tolerated per upstream ADR). |
| alpha.152 | 2026-04-20 | `bin/waaseyaa db:init` — sanctioned first-deploy database initializer. Idempotent, safe under `APP_ENV=production`. | Note in `migration-notes.md` as a new operational capability; no code change. |
| alpha.150 | 2026-04-19 | `FieldStorage::Column` vs `FieldStorage::Data` enum on `FieldDefinition`. Default is `Column`. | Indirect — when Giiken migrates to attribute-first registration, fields without dedicated columns must declare `stored: FieldStorage::Data` if they live in `_data`. Schema may already match expectations; verify in Phase 3. |
| alpha.148 | 2026-04-19 | Bundle-scoped storage substrate (`{base}__{bundle}` subtables). | None — Giiken does not use bundles. |
| alpha.157 | 2026-04-22 | `HttpKernel` JSON boot-failure detail mapping. | None — additive. |
| alpha.146 | 2026-04-18 | OIDC `/token` endpoint, OIDC `nonce` propagation. | None — Giiken doesn't implement OIDC. |
| alpha.146 | 2026-04-18 | `waaseyaa serve` now uses `public/index.php` as router script. | None — already works locally; will silently improve. |

### Low/no impact (framework-internal hygiene)

| Release | Date | Change | Reason no impact |
|---|---|---|---|
| alpha.173 | 2026-05-05 | Compatibility shim emits structured notices (already covered above) | — |
| alpha.172 | 2026-05-05 | `FieldDefinitionRegistry::registerCoreFields()` requires `targetEntityTypeId === $entityTypeId` invariant; fixed `groups`/`taxonomy` provider bind | Giiken doesn't use `groups` or `taxonomy`; doesn't construct `FieldDefinition` directly (will use `#[Field]` attributes after Phase 3) |
| alpha.171 | 2026-05-04 | Composer policy CP002/CP005/CP006 (`@dev` forbidden in framework root + `packages/*`) | Applies to framework, not consumers; Giiken's `waaseyaa/northcloud@dev` is in consumer composer.json |
| alpha.171 | 2026-05-04 | Root `composer.json` uses `self.version` for sibling constraints | Internal release engineering; consumers see only `waaseyaa/framework` published metadata |
| alpha.170 | 2026-05-03 | Removed root `"version": "1.1.0"` field (was breaking Packagist tag matching since alpha.145) | Internal release engineering |
| alpha.169 | 2026-05-03 | `skeleton-smoke` CI pins to exact upstream tag with retry loop | Internal CI |
| alpha.168 | 2026-05-03 | `FieldAttributeRule` moved from production autoload to `autoload-dev` | Internal — affects only consumers using PHPStan with the rule; Giiken does not opt into this rule yet |
| alpha.167 | 2026-05-03 | `SqlStorageDriver::write/read` splits values into columns vs `_data` JSON | Internal — improves consumer experience without API change |
| alpha.166 | 2026-05-03 | Packaged-form smoke CI | Internal CI |
| alpha.164 | 2026-04-28 | Split monorepo CI matrix fix (5 missing packages added) | Internal CI |
| alpha.163 | 2026-04-28 | Test bug fixes in `attachment` and `Phase4/FieldTypeDiscoveryTest` | Internal tests |
| alpha.157 | 2026-04-22 | (additive only — see medium-impact table) | — |
| alpha.153 | 2026-04-21 | `northcloud` sync status JSON enrichment; admin NC widget link | Floats with `@dev` constraint |
| alpha.152 | 2026-04-20 | (additive only — see medium-impact table) | — |
| alpha.151 | 2026-04-19 | Cross-package `waaseyaa/*` constraints tightened from `^0.1` to `^0.1.0-alpha.150` (54 manifests, 179 constraints) | Framework-internal; consumers benefit transparently |
| alpha.150 | 2026-04-19 | (FieldStorage enum already covered above) | — |
| alpha.149 | 2026-04-19 | `SqlSchemaHandler::shouldProcessBundles()` falls back to `FieldDefinitionRegistry::bundleNamesFor()` | Bundle-only; Giiken doesn't use bundles |
| alpha.148 | 2026-04-19 | (bundle substrate already covered above) | — |
| alpha.147 | 2026-04-18 | `NorthCloudServiceProvider` wires `allow_insecure` through to client | Floats with `@dev` constraint |
| alpha.146 | 2026-04-18 | (already covered above) | — |

## 2. Decision: Big-Bang Composer Bump

- **Decision:** Bump all 38 in-scope `waaseyaa/*` constraints from `^0.1.0-alpha.145` to `^0.1.0-alpha.173` in a single composer change (P1).
- **Rationale:** Upstream releases all 38 packages together at one tag. Each waaseyaa package's `composer.json` declares peer constraints on its waaseyaa siblings (e.g., `waaseyaa/access` requires `waaseyaa/entity ^0.1`); incremental bumps would force the resolver into mixed-version states that are not how upstream releases the framework. The path-repo override at `composer.local.json` already pins all 38 packages to the same monorepo HEAD, so this is the de-facto runtime mode regardless.
- **Alternatives considered:**
  - *Incremental, package-by-package* — rejected: peer constraints make this fight the resolver; produces no diagnostic value upstream wouldn't already have caught.
  - *Bisect by alpha tag* — rejected as default; held in reserve for Phase 5 if a particular alpha is implicated by an undocumented change.

## 3. Decision: Read-Then-Bump

- **Decision:** Read upstream `CHANGELOG.md` between `v0.1.0-alpha.145..v0.1.0-alpha.173` once, build a per-package change inventory in this document, then perform the bump (P2).
- **Rationale:** The changelog is well-structured (Keep-a-Changelog format with explicit ### Added / Changed / Fixed / Breaking sections per release). 28 alphas read once is faster and quieter than running tests through 28 alpha bumps. The targeted grep over Giiken's source then converts the changelog to concrete migration debt with file/method counts.
- **Alternatives considered:**
  - *Bump-and-see (no upfront diff)* — rejected: across 28 alphas × 38 packages × 6 contract surfaces (entity, controller, provider, route, response, access), the failure surface would be too noisy to triage efficiently.

## 4. Decision: Path Repo Authoritative Locally

- **Decision:** `composer.local.json` keeps the path-repo override active. `composer.lock` is committed and pinned to alpha.173 content hashes (which means `dev-main` plus a specific commit ref, since the path repo dominates resolution). Fresh-clone reproducibility against Packagist is **deferred to a future mission** (P3).
- **Rationale:** The maintainer's workflow is upstream-fix iteration via the symlinked monorepo. Forcing Packagist-only resolution this mission would block that workflow during the upgrade itself. A future mission can address Packagist-only installability once the upgrade is stable. The constraint bump still has value: it makes the published `composer.json` reflect the actual runtime version, removes silent absorption of breaking changes, and gives downstream tooling (e.g., GitHub dependabot) a real version to compare against.
- **Alternatives considered:**
  - *Remove `composer.local.json` entirely, force registry resolution* — rejected: blocks upstream-fix iteration during the very upgrade that surfaces the most upstream-fix needs.
  - *Use a VCS git repository entry instead of path repo* — rejected for now: still requires the upstream monorepo to be a known git remote, doesn't solve fresh-clone reproducibility, and adds a third resolution mode to manage.

## 5. Concrete Migration Debt — Grep Evidence

Run from `/home/jones/dev/giiken`:

```bash
grep -nE 'array \$params|array \$query|setKernelResolver|fieldDefinitions:|EntityType::fromClass|JsonResponseTrait|jsonApiResponse|json\(|jsonBody\(|#\[Field\]|#\[ContentEntityType\]|#\[MapRoute\]|#\[MapQuery\]' src/Provider/EntitiesProvider.php src/Http/Controller/DiscoveryController.php src/Http/Controller/QueryApiController.php src/Entity/KnowledgeItem/KnowledgeItem.php src/Entity/Community/Community.php
```

### Findings (2026-05-06 snapshot)

| File | Pattern | Count |
|---|---|---:|
| `src/Entity/KnowledgeItem/KnowledgeItem.php` | `fieldDefinitions:` | 1 |
| `src/Wiki/WikiLintReport.php` | `fieldDefinitions:` | 1 |
| `src/Entity/Community/Community.php` | none of the legacy patterns | 0 |
| `src/Http/Controller/DiscoveryController.php` | `array $params, array $query, ...` signature | 5 methods |
| `src/Http/Controller/ManagementController.php` | same legacy signature | 7 methods |
| `src/Http/Controller/QueryApiController.php` | same legacy signature | 3 methods |
| `src/Http/Controller/WebLoginController.php` | same legacy signature | 2 methods |
| `src/Http/Controller/WebLogoutController.php` | same legacy signature | 1 method |
| `src/Http/Controller/HomeController.php` | same legacy signature | 1 method |
| `src/Http/Controller/QueryApiController.php` | private `jsonBody()` helper (NOT framework trait) | 3 call sites + 1 declaration |
| `src/Http/Inertia/InertiaHttpResponder.php` | `->json(` | 2 call sites — investigated, unrelated to dropped `JsonResponseTrait` (returns Symfony JsonResponse) |
| any file under `src/` | `setKernelResolver` | 0 |
| any file under `src/` | `JsonResponseTrait` import | 0 |
| any file under `src/` | `EntityType::fromClass` | 0 (target state — Phase 3 introduces this) |
| any file under `src/` | `#[ContentEntityType]` / `#[Field]` / `#[MapRoute]` / `#[MapQuery]` | 0 (target state — Phases 3–4 introduce these) |

Totals:

- **2 entity files** to migrate to attribute-first (Phase 3).
- **6 controller files / 19 methods** to migrate to typed parameter injection (Phase 4).
- **0 service providers** need `setKernelResolver` migration.
- **0 controllers** use the dropped `JsonResponseTrait` surface.

## 6. Open Questions Resolved During Research

- **Q: Does Giiken's `Community` entity register fields the legacy way?** A: No. `Community.php` does not match `fieldDefinitions:`. Phase 3 confirms via grep at execution time; if the constructor uses any other registration path, it will surface during PHPUnit runs after the composer bump.
- **Q: Are there any `EntityTypeManager::assertClassMetadataMatchesEntityType()` call sites in Giiken?** A: None expected based on the canonical `AppServiceProvider` registration pattern. Verify during Phase 3.
- **Q: Does the `enum` field-type breakage in alpha.162 affect Giiken's existing entities?** A: Not in the current pre-attribute registration model — backed enums are stored via `KnowledgeItem`'s `$casts` array and the existing `string + enum_class` bridge. Once Phase 3 introduces `#[Field]` attributes on enum-typed properties, the new `enum` field-type plugin (alpha.162) takes effect transparently via inference. No additional adaptation needed beyond what Phase 3 already does.
- **Q: Does the alpha.165 schema-evolution v2 require migration ledger reshape?** A: The new `checksum` and `diff_hash` columns are nullable and added by upstream's idempotent backfill. No Giiken migration required. Verify by running `./vendor/bin/waaseyaa migrate` in Phase 1 baseline and Phase 6 verification.

## 7. Outputs

- This file (`research.md`) — per-package decision matrix and migration debt grep.
- Phase 1 will produce `baseline.md` documenting pre-upgrade state.
- Phase 5–6 will produce `migration-notes.md` documenting per-adapted-contract resolutions.
