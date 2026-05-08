# Implementation Plan: Eliminate Symfony-shaped behavior — ingestion + compilation CLI path

**Mission:** `eliminate-symfony-ingestion-cli-path-01KR2JYJ` (`01KR2JYJ0PHKCN2VEHV0QQSBA5`)  
**Spec:** [spec.md](./spec.md)  
**Contract:** [docs/specs/giiken-ingestion-cli-contract.md](../../docs/specs/giiken-ingestion-cli-contract.md)

## Summary

1. **Bump and align** — Pin `waaseyaa/*` to **`^0.1.0-alpha.174`** (or newer patch) once available; re-read **`HasCommandsInterface`**, **`ConsoleKernel`**, and any new **`Cli*` / command base** types. Confirm the **only** allowed Symfony imports at the CLI edge.
2. **Command rewrite** — Refactor **`IngestFileCommand`** to the Waaseyaa console contract: configuration, **`execute()`** equivalent, status codes from Waaseyaa (not **`Command::SUCCESS`** literals if replaced), and IO without Symfony **`OutputInterface`** styling if a Waaseyaa helper exists.
3. **Pipeline exception boundary** — Centralize translation from **`StepResult`** failure + inner **`RuntimeException`** to **`PipelineException`** (already partially present); ensure **no** `Symfony\Component\...\Exception` leaks from steps or providers.
4. **Registry + handlers** — Audit **`IngestionHandlerRegistry`** and handlers used by **`giiken:ingest:file`** for Symfony types, array callables, and resolvers; fix or document exceptions in **`docs/specs/giiken-ingestion-cli-contract.md`**.
5. **DI** — **`AppServiceProvider::commands()`**: ensure **`IngestFileCommand`** is constructed with **explicit constructor injection** only (already true); adjust return type if **`HasCommandsInterface`** changes.
6. **Tests** — Add **`tests/Unit/Architecture/`** (or **`tests/Contract/`**) guard test for forbidden imports; update **`IngestFileCommandTest`** to use Waaseyaa CLI test harness or isolated Symfony adapter per spec.
7. **Lifecycle** — If **`AppServiceProvider`**, **`IngestFileCommand`**, pipeline, or handler signatures change behavior visible to operators, update **`docs/architecture/lifecycle.md`** and satisfy drift check.

## Technical context

| Item | Value |
|------|--------|
| Language | PHP 8.4+ |
| App | Giiken on Waaseyaa |
| Entry | `./vendor/bin/waaseyaa giiken:ingest:file` |
| Tests | PHPUnit 10.5+ |
| Static analysis | PHPStan on `src/` |

## Verification gates

- `./vendor/bin/phpunit`
- `./vendor/bin/phpstan analyse src/`
- Optional: `npm run test:js` if no frontend change
- `scripts/check-lifecycle-drift.sh` when lifecycle files touch

## Complexity tracking

None — scope is intentionally narrow to the ingest-file path.
