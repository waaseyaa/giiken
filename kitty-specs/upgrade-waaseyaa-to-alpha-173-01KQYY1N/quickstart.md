# Quickstart — Upgrade Waaseyaa to alpha.173

**Mission:** `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Audience:** maintainer running the verification pass at any phase boundary.

This is the **runbook** the upgrade plan uses to check pre-upgrade state, post-bump state, and final-green state. Running it should always produce the same three outcomes: PHPUnit green, PHPStan clean, smoke 200/200.

---

## Prerequisites

- Working directory: `/home/jones/dev/giiken`
- Upstream waaseyaa monorepo present at `../waaseyaa` (symlinked via `composer.local.json`)
- Composer installed; PHP 8.4+ on `$PATH`
- Port 8080 free (or pick another and adjust)

---

## 1. Capture the test baseline

```bash
./vendor/bin/phpunit
```

Record:
- Exit code (expect `0`)
- Test count (`Tests: NN, Assertions: MM`)
- Wall time (`Time: HH:MM.NNN`)

## 2. Capture the static-analysis baseline

```bash
./vendor/bin/phpstan analyse src tests --level=8 --no-progress
```

Record finding count (expect `[OK] No errors`).

## 3. Run the boot-to-browser smoke

```bash
./vendor/bin/waaseyaa migrate
./vendor/bin/waaseyaa giiken:seed:test-community
./vendor/bin/waaseyaa serve   # or: php -S 127.0.0.1:8080 -t public public/index.php
# in another shell:
curl -i http://127.0.0.1:8080/
curl -i http://127.0.0.1:8080/test-community
```

Verify:
- `curl /` returns `200 OK` with an Inertia JSON or HTML payload identifying the "Discover" page.
- `curl /test-community` returns `200 OK` with the seeded community's `Discovery/Index` page including the 3 seeded knowledge items.
- `curl /` latency under 2 seconds (NFR-003).

Stop the server (`Ctrl-C` or `kill`) once verified.

---

## 4. Frontend tests (sanity, optional during phase boundaries)

```bash
npm run test:js
```

Frontend tests are not the primary gate for this mission (frontend deps are out of scope per C-003), but a green Vitest run confirms no accidental coupling broke.

---

## 5. Phase-boundary checks

| When | What to look for |
|---|---|
| **Phase 1 baseline** | All three gates green; record exact numbers in `baseline.md` |
| **After Phase 2 composer bump** | Expect failures; capture the failure surface in `baseline.md` (entity-registration-class, controller-signature-class, other) |
| **After Phase 3 entity migration** | Entity-registration-class failures gone; controller-signature-class failures may remain |
| **After Phase 4 controller migration** | All test failures resolved; no `implicit_array_unbound` deprecation notices in logs |
| **Phase 5** | Any residual failure adapted via upstream fix; documented in `migration-notes.md` |
| **Phase 6 final** | All three gates green; `scripts/check-lifecycle-drift.sh` passes; `migration-notes.md` complete; `CLAUDE.md` § "Boot-to-browser status" updated |

---

## 6. Lifecycle drift gate (Phase 6)

```bash
./scripts/check-lifecycle-drift.sh
```

If lifecycle-impacting files changed (HTTP controllers, providers, entities, pipeline), the script will flag drift. Update `docs/architecture/lifecycle.md` to match the migrated state and re-run.

---

## 7. Commit hygiene

Each phase ends in at least one commit. Suggested conventional-commit prefixes:

| Phase | Prefix | Example |
|---|---|---|
| P1 | `chore(upgrade):` | `chore(upgrade): capture pre-alpha-173 baseline` |
| P2 | `chore(deps):` | `chore(deps): bump waaseyaa/* to ^0.1.0-alpha.173` |
| P3 | `refactor(entity):` | `refactor(entity): migrate KnowledgeItem to attribute-first registration` |
| P4 | `refactor(http):` | `refactor(http): migrate DiscoveryController to typed parameter injection` |
| P5 | `fix(upstream):` | `fix(upstream): adapt to <contract> change in alpha.X` (if upstream fix is also pulled) |
| P6 | `docs(upgrade):` | `docs(upgrade): finalize migration notes for alpha.173` |

---

## Rollback

Path-repo resolution makes this fully reversible: `git revert` the composer bump commit and run `composer update 'waaseyaa/*'`. The upstream symlink can also be checked out at the prior tag (`git -C ../waaseyaa checkout v0.1.0-alpha.145`) for emergency parity.
