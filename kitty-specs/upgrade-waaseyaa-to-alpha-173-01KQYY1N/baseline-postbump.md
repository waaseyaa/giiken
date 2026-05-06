# Post-Bump Failure Surface — Upgrade Waaseyaa to alpha.173

**Captured:** 2026-05-06T17:02:00Z
**Captured by:** WP02 (T007)
**Pre-bump baseline:** see [baseline.md](./baseline.md)

## Composer state

- `composer.json`: 33 in-scope `waaseyaa/*` constraints rewritten from `^0.1.0-alpha.145` to `^0.1.0-alpha.173`. `waaseyaa/northcloud` unchanged at `@dev` (FR-003 preserved).
- **Deviation from WP prompt expectation of 38:** the actual count of `^0.1.0-alpha.145` lines in `composer.json` was 33, not 38. The WP prompt's `~38` was a planning estimate (note the `~` in the subtask body); 33 is the true count of in-scope `waaseyaa/*` packages declared in `require:`. All 33 were rewritten; zero `^0.1.0-alpha.145` lines remain.
- `composer.lock`: regenerated with `composer update 'waaseyaa/*' --with-all-dependencies` (exit 0). 113 lock operations across the dependency graph; 39 `waaseyaa/*` packages now resolve to `v0.1.0-alpha.173` (the 33 directly required + transitive: `waaseyaa/northcloud`, `waaseyaa/relationship`, `waaseyaa/oidc`, `waaseyaa/error-handler`, `waaseyaa/http-client`, `waaseyaa/oauth2-server`).
- `waaseyaa/core` lockfile reference: `80e08bc1bd756e0c5c93b665562e1609070197e6` (the v0.1.0-alpha.173 split-repo commit for the `core` subtree). The upstream monorepo HEAD is `38feb0fbe20d99380b9951750ea73224e21620df` (`v0.1.0-alpha.173`); these diverge because Composer fetched packagist archives, not via path repo. **Reason:** `composer.local.json` is a gitignored runtime file present only in the main repo, not in the lane worktree. The packagist tags are authoritative for the constraint, so this is acceptable per planning decision P3 (registry-version hygiene).
- Upstream symlink path: `/home/jones/dev/waaseyaa` @ `v0.1.0-alpha.173` (commit `38feb0fbe20d99380b9951750ea73224e21620df`).

## PHPUnit post-bump

- Exit code: 0
- Tests: 258 (baseline: 258) — no change
- Failures: 0
- Errors: 0
- Deprecations: 2 (baseline: 2) — no change
- Wall time: 1.542s

### Failure categories (per research.md predictions)

- entity-registration (alpha.162 — `fieldDefinitions:` dropped): **0**
- controller-signature (alpha.162 — `array $params`/`array $query` dropped): **0**
- other / unpredicted: **0**

**Result:** zero failures. The predicted failure surface from `research.md` did not materialize. Giiken's `src/` already uses the post-alpha.162 attribute-based entity registration and modern controller signatures, so the constraint bump was a clean uplift.

## PHPStan post-bump

- Exit code: 0
- Findings: 45 (baseline: 45) — no change
- New findings beyond baseline: 0
- Distinct error classes: pre-existing object-type-narrowing issues in `Bin/Console/*`, `Pipeline/CompilationPipeline`, `Query/SynthesisService`, `Export/ExportService`, and `tests/Integration/Entity/ContentEntitySqlIntegrationTest.php`. No new diagnostics introduced by alpha.173.

## Smoke post-bump

Server: `php -S 127.0.0.1:8081 -t public public/index.php` against `storage/waaseyaa.sqlite` copied from main repo (gitignored runtime file).

- `/`: HTTP 200, 425 bytes (Inertia `Discover` payload with seeded `sagamok-anishnawbek` community)
- `/sagamok-anishnawbek`: HTTP 200, 3245 bytes
- `/sagamok-anishnawbek/item/30`: HTTP 200, 1773 bytes
- `/sagamok-anishnawbek/search?q=water`: `totalHits=2`

All three Inertia routes 200; search FTS returns expected hits. Real-content smoke is fully green on alpha.173.

## Deviation from research.md predictions

**Significant deviation:** research.md predicted two failure categories post-bump (entity-registration via dropped `fieldDefinitions:`, and controller-signature via dropped `array $params`/`array $query`). Both predictions assumed Giiken's `src/` and `tests/` still used the legacy patterns. **Reality: neither category fires.**

Plausible causes:
- Giiken's source already migrated to the attribute-first entity API and the modern `WaaseyaaRouter` controller signatures in earlier work (the repo has been tracking the path-repo HEAD via `composer.local.json` which has been at `v0.1.0-alpha.173` for some time, so the migrations were applied silently as the framework code evolved under the symlink).
- The constraint string in `composer.json` was the only thing actually pinned to `^0.1.0-alpha.145`; the running framework code was already alpha.173 via the path overlay, so the test suite has effectively been validating against alpha.173 for a while.

**Implication for downstream WPs (WP03, WP04, WP05):** the per-category remediation WPs may have nothing to remediate. Reviewers should consult this baseline-postbump.md before kicking off WP03/WP04/WP05 to decide whether those WPs collapse to no-ops or close out as "no work required, baseline already clean." The `research.md` predictions stand as documentation of the *theoretical* breakage surface for alpha.145 → alpha.173 in a Giiken-shaped consumer; they did not match the *actual* state of this repo.

## Files changed in this WP

- `composer.json` — 33 lines (`^0.1.0-alpha.145` → `^0.1.0-alpha.173`)
- `composer.lock` — regenerated (113 lock operations, 39 `waaseyaa/*` packages at v0.1.0-alpha.173)
- `kitty-specs/upgrade-waaseyaa-to-alpha-173-01KQYY1N/baseline-postbump.md` — this file
