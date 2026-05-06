---
work_package_id: WP04
title: 'Controller migration: Discovery + Auth'
dependencies:
- WP02
requirement_refs:
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T015
- T016
- T017
- T018
phase: Phase 4 - Story (alpha.162/.173 controller signature adaptation)
assignee: ''
agent: ''
history:
- timestamp: '2026-05-06T16:14:17Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: src/Http/Controller/
execution_mode: code_change
owned_files:
- src/Http/Controller/HomeController.php
- src/Http/Controller/WebLogoutController.php
- src/Http/Controller/WebLoginController.php
- src/Http/Controller/DiscoveryController.php
- tests/Unit/Http/Controller/HomeControllerTest.php
- tests/Unit/Http/Controller/WebLogoutControllerTest.php
- tests/Unit/Http/Controller/WebLoginControllerTest.php
- tests/Unit/Http/Controller/DiscoveryControllerTest.php
tags: []
---

# Work Package Prompt: WP04 — Controller migration: Discovery + Auth

## Objective

Migrate 4 controllers (9 method signatures total) from the legacy `array $params, array $query, AccountInterface $account, HttpRequest $httpRequest` signature dropped in waaseyaa alpha.162 to **typed parameter injection** with `#[MapRoute]` and `#[MapQuery]` attributes per the alpha.173 shim guidance.

## Branch Strategy

Trunk-based on `main`. Depends on WP02 (composer bump). May execute in parallel with WP03 (entities) and WP05 (other controllers) — disjoint file ownership.

## Context

- Migration target pattern (alpha.162+):

  ```php
  // BEFORE (legacy, fires alpha.173 deprecation notices):
  public function index(
      array $params,
      array $query,
      AccountInterface $account,
      HttpRequest $httpRequest,
  ): Response {
      $communitySlug = $params['communitySlug'];
      $q = $query['q'] ?? null;
      // ...
  }

  // AFTER (typed parameter injection):
  public function index(
      string $communitySlug,
      #[MapQuery] ?string $q,
      AccountInterface $account,
      HttpRequest $httpRequest,
  ): Response {
      // ...
  }
  ```

- `#[MapRoute]` is implicit when a typed parameter name matches a route placeholder (e.g., `string $communitySlug` for route `/{communitySlug}/...`).
- `#[MapQuery]` must be declared explicitly for query-string parameters.
- Services and the request itself are injected by type hint (no attribute needed).
- Use statements to add: `Waaseyaa\Routing\Attribute\MapQuery` (and `MapRoute` if explicit binding is needed).
- alpha.173 introduced a compatibility shim that lets the legacy signature still work but fires structured `LoggerInterface::notice` events. **The project's "no workarounds" doctrine requires us to migrate fully so zero notices fire.**

## Subtasks

### T015 — Migrate `HomeController::discover()` [P]

**Purpose:** Migrate the simplest controller (1 method, no route or query parameters).

**Steps:**

1. Open `src/Http/Controller/HomeController.php`.
2. Inspect `discover(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response`.
3. The route `giiken.home` is `GET /` — no route placeholders, no query parameters that the controller currently reads.
4. Rewrite to drop `array $params, array $query`. Keep `AccountInterface $account, HttpRequest $httpRequest` (these are services / the request, untouched by the migration).
5. Remove any `$params['...']` / `$query['...']` accesses inside the method body — there should be none for this controller.

**Files:**

- `src/Http/Controller/HomeController.php`

**Validation:**

- `grep -c 'array \$params\|array \$query' src/Http/Controller/HomeController.php` returns 0.
- Smoke `curl -i http://127.0.0.1:8080/` returns 200 (or PHPUnit `HomeControllerTest` passes if one exists).

### T016 — Migrate `WebLogoutController::logout()` [P]

**Purpose:** Migrate the auth-logout controller (1 method, no parameters).

**Steps:**

1. Open `src/Http/Controller/WebLogoutController.php`.
2. The route `giiken.logout` is `POST /logout` — no route placeholders, no expected query parameters.
3. Rewrite the signature to drop `array $params, array $query`. Keep `AccountInterface $account, HttpRequest $httpRequest`.
4. Remove any `$params`/`$query` accesses inside the method body.

**Files:**

- `src/Http/Controller/WebLogoutController.php`

**Validation:**

- `grep -c 'array \$params\|array \$query' src/Http/Controller/WebLogoutController.php` returns 0.
- POST `/logout` continues to return the expected redirect (verify in T021 / smoke).

### T017 — Migrate `WebLoginController::showForm()` and `submit()` [P]

**Purpose:** Migrate the auth-login controller (2 methods).

**Steps:**

1. Open `src/Http/Controller/WebLoginController.php`.
2. The route group is `GET /login` (showForm) and `POST /login` (submit) — no route placeholders.
3. For each method:
   - Drop `array $params, array $query`.
   - For `submit()`, the form body comes from `HttpRequest`; preserve any existing access pattern that uses `$httpRequest->request->get(...)` or similar Symfony Request API.
   - Keep `AccountInterface` and `HttpRequest` injections.
4. Remove `$params`/`$query` accesses in both method bodies.

**Files:**

- `src/Http/Controller/WebLoginController.php`

**Validation:**

- `grep -c 'array \$params\|array \$query' src/Http/Controller/WebLoginController.php` returns 0.
- GET `/login` renders form; POST `/login` accepts credentials (verify via PHPUnit if test exists, otherwise reasoning only).

### T018 — Migrate `DiscoveryController` (5 methods)

**Purpose:** The largest controller in this WP. All 5 methods consume `$params['communitySlug']`; `show()` additionally consumes `$params['itemId']`; `search()` and `ask()` may consume `$query['q']` or similar.

**Steps:**

1. Open `src/Http/Controller/DiscoveryController.php`.
2. Add `use Waaseyaa\Routing\Attribute\MapQuery;` at the top of the file.
3. For each method (`index`, `search`, `ask`, `show`, plus any internal helper bound to a route):
   - **Replace `array $params`** with explicit typed parameters that match the route placeholder names. The route patterns are `/{communitySlug}`, `/{communitySlug}/search`, `/{communitySlug}/ask`, `/{communitySlug}/item/{itemId}`. So:
     - All methods get `string $communitySlug`.
     - `show()` additionally gets `string $itemId` (note: route requirement is `.+`, so the type stays `string`).
   - **Replace `array $query`** with `#[MapQuery]` annotations on typed query parameters. For example, if `search()` reads `$query['q']`:
     ```php
     public function search(
         string $communitySlug,
         #[MapQuery] ?string $q,
         AccountInterface $account,
         HttpRequest $httpRequest,
     ): Response
     ```
   - **Inside each method body**, replace `$params['communitySlug']` with `$communitySlug`, `$params['itemId']` with `$itemId`, `$query['q']` with `$q`, etc.
4. If a method uses no query parameters, drop `array $query` entirely.

**Files:**

- `src/Http/Controller/DiscoveryController.php`

**Validation:**

- `grep -c 'array \$params\|array \$query' src/Http/Controller/DiscoveryController.php` returns 0.
- Smoke path returns 200 for `/test-community`, `/test-community/search`, `/test-community/ask`, `/test-community/item/<itemId>`.
- PHPUnit `DiscoveryControllerTest` (and any integration controller test) passes.

### T015–T018 wrap-up — Commit

After all four subtasks pass validation, commit:

```bash
git add src/Http/Controller/HomeController.php \
        src/Http/Controller/WebLogoutController.php \
        src/Http/Controller/WebLoginController.php \
        src/Http/Controller/DiscoveryController.php \
        tests/Unit/Http/Controller/
git commit -m "refactor(http): migrate Discovery + Auth controllers to typed parameter injection (WP04)"
```

(WP05's verification step T021 will confirm zero deprecation notices fire across all 6 controllers — that's the cross-WP gate.)

## Definition of Done

- [ ] `HomeController`, `WebLogoutController`, `WebLoginController`, `DiscoveryController` all have zero unannotated `array $params` or `array $query` parameters.
- [ ] Route dispatch for the routes owned by these controllers (`/`, `/login` GET+POST, `/logout`, `/{slug}`, `/{slug}/search`, `/{slug}/ask`, `/{slug}/item/{itemId}`) returns 200 (or expected status) under smoke.
- [ ] PHPUnit tests for these controllers pass.
- [ ] One commit on `main` (or one per controller — maintainer choice; conventional-commit format either way).

## Risks

- **Route placeholder name vs. parameter name mismatch.** If the route declares `{slug}` but the controller method declares `string $communitySlug`, the framework cannot bind. Either rename the route placeholder (changes RoutesProvider) or rename the parameter. Prefer matching the route's existing names — Giiken uses `communitySlug` consistently, so this should be fine.
- **DiscoveryController::show() route requirement `.+`.** The route allows path-like itemIds (per RoutesProvider). The PHP type `string` accepts these correctly. Don't try to type as `int` or a UUID — `string` is the contract.
- **Form-encoded body in `WebLoginController::submit`.** If the legacy code reads from `$httpRequest->request->get(...)`, that still works post-migration. If it read from `$query['_method']` or similar, migrate to `HttpRequest` access. Re-read the method body after editing the signature.

## Reviewer Guidance

- For each migrated method, confirm the body's parameter accesses match the new typed parameter names (no orphan `$params['...']` references).
- Confirm `use` statements are clean — `MapQuery` imported only if used.
- Confirm no controller from WP05's scope (`ManagementController`, `QueryApiController`) was modified.
- Smoke-curl all the routes owned by these controllers and verify 200 response.

## Implementation Command

```bash
spec-kitty agent action implement WP04 --agent <agent-name>
```

(Depends on WP02.)
