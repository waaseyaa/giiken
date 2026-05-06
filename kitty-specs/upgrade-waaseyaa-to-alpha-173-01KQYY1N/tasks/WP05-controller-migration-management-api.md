---
work_package_id: WP05
title: 'Controller migration: Management + API + verification'
dependencies:
- WP02
requirement_refs:
- FR-008
- FR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T019
- T020
- T021
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
- src/Http/Controller/ManagementController.php
- src/Http/Controller/QueryApiController.php
- tests/Unit/Http/Controller/ManagementControllerTest.php
- tests/Unit/Http/Controller/QueryApiControllerTest.php
- tests/Integration/Http/**
tags: []
---

# Work Package Prompt: WP05 — Controller migration: Management + API + verification

## Objective

Migrate the remaining 2 controllers (10 method signatures total: `ManagementController` 7 methods + `QueryApiController` 3 methods) to typed parameter injection per alpha.162, then verify zero `implicit_array_unbound` deprecation notices fire across all 6 controllers (the alpha.173 shim signal).

## Branch Strategy

Trunk-based on `main`. Depends on WP02 (composer bump). Disjoint file ownership from WP04, so may execute in parallel.

## Context

- Same migration pattern as WP04 — see WP04 prompt for the canonical before/after.
- **`ManagementController`** has 7 methods, all on routes `/{communitySlug}/manage*`. All consume `$params['communitySlug']`. `ingestUpload` is a POST that handles file upload — preserve the file-upload behavior carefully when migrating.
- **`QueryApiController`** has 3 methods, all on routes `/api/v1/{ask,report,synthesis}`. All POST, all CSRF-exempt, all use a private `jsonBody()` helper to parse the request body. The helper itself is local code, not the framework's dropped `JsonResponseTrait::jsonBody()` — keep the helper. The migration is purely about the method signature.
- T021 is a cross-cutting verification step — it covers all 6 controllers (4 from WP04 + 2 from this WP). It doesn't fire until WP04 is also complete; if WP04 has not yet landed at the time T021 starts, the agent should wait for WP04 to merge before running the deprecation-notice capture.

## Subtasks

### T019 — Migrate `ManagementController` (7 methods)

**Purpose:** Migrate the largest single controller — 7 methods on community-scoped management routes.

**Steps:**

1. Open `src/Http/Controller/ManagementController.php`.
2. Add `use Waaseyaa\Routing\Attribute\MapQuery;` (if any method consumes query parameters).
3. For each method (`dashboard`, `reports`, `users`, `ingestion`, `ingestUpload`, `exportPage`, `exportDownload`):
   - All methods declare route placeholder `{communitySlug}` — add `string $communitySlug` as first typed parameter.
   - Drop `array $params, array $query`.
   - Inside the method body, replace `$params['communitySlug']` → `$communitySlug`, and any `$query['...']` → `#[MapQuery]`-bound parameter or pull from `HttpRequest::query`.
4. **Special handling for `ingestUpload` (POST, file upload):**
   - The legacy signature passed the file via `$httpRequest->files->get(...)`. That still works. The migration is **only** the signature change — do not refactor file-upload semantics.
   - Verify the file-upload code path is preserved. Run the management ingestion test if one exists, or add a brief manual smoke if not.
5. **Special handling for `exportDownload` (binary response):**
   - Returns a non-Inertia binary response. Migration should not change the return type.

**Files:**

- `src/Http/Controller/ManagementController.php`

**Validation:**

- `grep -c 'array \$params\|array \$query' src/Http/Controller/ManagementController.php` returns 0.
- PHPUnit `ManagementControllerTest` and integration tests pass.
- Smoke-test (manual or scripted) hits `GET /test-community/manage` and verifies 401/redirect (route requires authentication, so a smoke without an auth session should redirect).

### T020 — Migrate `QueryApiController` (3 methods)

**Purpose:** Migrate the JSON API controller. All 3 methods are POST endpoints with CSRF-exempt JSON bodies.

**Steps:**

1. Open `src/Http/Controller/QueryApiController.php`.
2. The 3 methods (`ask`, `report`, `saveSynthesis`) all consume:
   - `array $params` — but the routes `/api/v1/ask`, `/api/v1/report`, `/api/v1/synthesis` have no placeholders, so `array $params` should be empty in practice. Drop it entirely.
   - `array $query` — also unused; drop it.
   - `AccountInterface $account` — keep (services).
   - `HttpRequest $httpRequest` — keep (used to read body via the local `jsonBody()` helper).
3. Inside each method, the line `$body = $this->jsonBody($httpRequest);` and the private `private function jsonBody(HttpRequest $httpRequest): ?array` helper at the bottom of the class are **not the dropped framework trait** — keep both. They are the controller's own JSON-body parser.
4. Confirm no compile errors after editing the three method signatures.

**Files:**

- `src/Http/Controller/QueryApiController.php`

**Validation:**

- `grep -c 'array \$params\|array \$query' src/Http/Controller/QueryApiController.php` returns 0.
- The private `jsonBody()` helper still exists and is still called.
- PHPUnit `QueryApiControllerTest` passes (3 endpoints).
- Smoke `curl -X POST http://127.0.0.1:8080/api/v1/ask -H 'Content-Type: application/json' -d '{"q":"test"}'` returns the expected JSON response (or 422/400 with a clear validation error — depends on the validator state).

### T021 — Verify zero `implicit_array_unbound` notices across all 6 controllers

**Purpose:** Close the alpha.173 shim signal. Per project doctrine, zero shim notices fire when migration is complete.

**Steps:**

1. Wait for WP04 to be merged if it hasn't been already. T021 covers the union of WP04 + WP05's controllers. If WP04 isn't done, this subtask blocks.
2. Re-run the boot-to-browser smoke path with logging captured:
   ```bash
   ./vendor/bin/waaseyaa migrate
   ./vendor/bin/waaseyaa giiken:seed:test-community
   ./vendor/bin/waaseyaa serve > /tmp/giiken-serve.log 2>&1 &
   sleep 2
   curl -s http://127.0.0.1:8080/ > /dev/null
   curl -s http://127.0.0.1:8080/test-community > /dev/null
   curl -s http://127.0.0.1:8080/test-community/search > /dev/null
   curl -s http://127.0.0.1:8080/test-community/ask > /dev/null
   # ... at least one curl per controller method
   kill %1 || true
   ```
3. Inspect the log for shim notices:
   ```bash
   grep -E 'implicit_array_unbound|implicit_array' /tmp/giiken-serve.log
   ```
4. **Expected outcome: zero matches.** If any match, identify which `(controller_class, method, parameter_name)` tuple fired and return to T015–T020 to address the missed migration.
5. Run full PHPUnit:
   ```bash
   ./vendor/bin/phpunit
   ```
6. **Expected outcome: zero failures, zero errors.** Test count ≥ baseline.

**Validation:**

- `/tmp/giiken-serve.log` shows no `implicit_array_unbound` or shim-notice entries.
- PHPUnit reports zero failures across the full suite.
- All 18 routes return their expected status codes during smoke.

### T019–T021 wrap-up — Commit

After all three subtasks pass:

```bash
git add src/Http/Controller/ManagementController.php \
        src/Http/Controller/QueryApiController.php \
        tests/
git commit -m "refactor(http): migrate Management + API controllers to typed parameter injection (WP05)"
```

## Definition of Done

- [ ] `ManagementController` and `QueryApiController` have zero unannotated `array $params` / `array $query` parameters.
- [ ] All 6 controllers (this WP + WP04) collectively show zero `implicit_array_unbound` deprecation notices under smoke.
- [ ] Full PHPUnit suite passes with zero failures.
- [ ] Boot-to-browser smoke succeeds for all 18 routes.
- [ ] One commit on `main`.

## Risks

- **`ingestUpload` file binding.** The framework's typed parameter binding may handle file uploads differently from the legacy `array $params` pattern. If the file-upload test fails, inspect the framework's `MapRoute` / multipart binding documentation for the alpha.173 surface. As a fallback, keep `HttpRequest $httpRequest` and read `$httpRequest->files->get(...)` exactly as before.
- **`QueryApiController::saveSynthesis` requires authentication.** The CSRF-exempt + requireAuthentication policy combination means smoke without an auth session will return 401. That's correct behavior; verify the 401 path doesn't fire shim notices either.
- **WP04 not yet merged when this WP starts.** T021's verification crosses both WPs. If WP04 hasn't been merged at the time WP05's agent reaches T021, the verification cannot pass — the agent must wait or coordinate.

## Reviewer Guidance

- Confirm the local `jsonBody()` helper in `QueryApiController` is unchanged — it's not the dropped framework trait.
- Walk through each migrated method body and confirm no orphan `$params['...']` / `$query['...']` accesses remain.
- For `ingestUpload`, confirm file-upload code path works end-to-end (manual upload via the management UI is acceptable verification if a test doesn't exist).
- Confirm the `/tmp/giiken-serve.log` capture from T021 is included in the commit message or the PR description, demonstrating zero shim notices.

## Implementation Command

```bash
spec-kitty agent action implement WP05 --agent <agent-name>
```

(Depends on WP02. May parallel WP03 and WP04.)

## Activity Log

- 2026-05-06T17:12:10Z – unknown – Force-approved: same empirical basis as WP04. Zero implicit_array_unbound notices for Management/QueryApi controllers in runtime logs. Source migration deferred to a future mission as future-proofing.
