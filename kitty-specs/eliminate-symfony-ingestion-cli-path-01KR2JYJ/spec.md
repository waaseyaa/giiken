# Spec: Eliminate Symfony-shaped behavior — ingestion + compilation CLI path

**Mission:** `01KR2JYJ0PHKCN2VEHV0QQSBA5` — `eliminate-symfony-ingestion-cli-path-01KR2JYJ`  
**Type:** `software-dev`  
**Merge target:** `main`  
**Waaseyaa baseline:** `^0.1.0-alpha.174` (foundation routing/dispatch contracts per upstream release notes; bump Composer when tag is available)

## Problem

Giiken’s operator path for **file ingestion** runs synchronously from **`giiken:ingest:file`** through **`IngestFileCommand`** → **`IngestionHandlerRegistry`** → concrete **`FileIngestionHandlerInterface`** implementations → **`CompilationPipeline`** → **`KnowledgeItem`** persistence (via pipeline steps, notably **`EmbedStep`**).

After alpha.174, Waaseyaa’s **HTTP dispatch and service wiring** no longer rely on Symfony-shaped fallbacks (implicit array binding, legacy resolver behavior, etc.). The ingestion CLI path should match the same discipline: **no Symfony coupling beyond what the Waaseyaa CLI kernel explicitly contracts**, and **no Symfony exception or invocation patterns** in domain flow.

Today, **`IngestFileCommand`** is a **`Symfony\Component\Console\Command\Command`** with **`InputInterface` / `OutputInterface`**, **`CommandTester`**-based tests, and Symfony exit constants. Handlers and **`CompilationPipeline`** are already mostly free of Symfony types, but the **command boundary** and any **hidden Symfony defaults** (e.g. wrapping step failures in generic **`RuntimeException`** without a Giiken pipeline contract) must be **inventory-complete and normalized** to Waaseyaa-first types and identifiers.

## Goals (must)

1. **Inventory** every Symfony (or Symfony-shaped) touchpoint in the scoped path: command class, DI registration sites that feed this command only, registry, handlers invoked from the registry for ingest-file MIME types, pipeline and steps, persistence in that path, and unit/integration tests that exercise this path.
2. **Remove Symfony fallback behavior** where Waaseyaa exposes a first-class alternative:
   - No **`[$object, 'method']`** or other array callables for framework entrypoints/resolution in this path.
   - No **`Symfony\Component\*Exception\*`** (or other Symfony exception types) for domain or pipeline failures in this path — use **`App\Ingestion\IngestionException`**, **`App\Pipeline\PipelineException`**, or **`Throwable`** contracts documented below.
   - No reliance on Symfony-specific **resolver** or **parameter** injection behavior in ingestion/pipeline code.
3. **Normalize framework-facing identifiers** to Waaseyaa contracts:
   - CLI command name stays **`giiken:ingest:file`** (Waaseyaa console application registration).
   - **`PipelineContext::pipelineId`** and step descriptors remain explicit strings owned by Giiken; document canonical values in **`docs/specs/giiken-ingestion-cli-contract.md`**.
   - Handler selection remains **MIME-based** on **`FileIngestionHandlerInterface::supports`**; if MIME strings move to shared enums/constants in Waaseyaa later, consume those — do not invent parallel Symfony conventions.
4. **Console boundary**: Implement **`IngestFileCommand`** using the **Waaseyaa-supported console command surface** (e.g. base class / attribute / registration API provided by **`waaseyaa/cli`** + **`HasCommandsInterface`** after alpha.174). If the kernel still types **`list<Symfony\Component\Console\Command\Command>`**, that is the **single allowed Symfony touchpoint** at the adapter edge; **domain code** and **orchestration inside the command** must not import Symfony beyond that edge once alternatives exist.
5. **Tests** must assert that **no Symfony fallback paths** are reachable in **production code** for **ingestion → handler → pipeline → persistence**:
   - Add an **architecture or focused PHPUnit test** that fails if forbidden `use Symfony\...` imports appear under owned directories (see **Owned file boundaries**), with an explicit allowlist only for the **documented console adapter seam** if still required by **`HasCommandsInterface`**.
   - Extend or replace **`CommandTester`** usage if Waaseyaa provides a **`CliCommandTester`** or application harness; if not, isolate Symfony **`Application`/`CommandTester`** to a **single test helper** file marked as **test-only adapter**, not production.

## Non-goals (must not — mission creep)

- Migrating **`search:reindex`**, **`giiken:seed:test-community`**, or other Giiken console commands unless required to satisfy **`HasCommandsInterface`** return type changes (if so, limit diffs to **shared typing** only; do **not** rewrite unrelated command bodies).
- Replacing **`symfony/yaml`** in **`MarkdownIngestionHandler`** purely for branding; YAML parsing is **not** resolver/dispatch fallback. Reopen only if it is used for **routing-like** behavior.
- **`App\Ingestion\Upload\*`** and **`UploadedFile`** (HTTP) — not on the **`giiken:ingest:file`** path.
- NorthCloud ingestion, Management UI upload, or async jobs.

## Functional requirements (traceability)

| ID | Requirement | WP |
|----|-------------|-----|
| FR-001 | Publish and maintain `docs/specs/giiken-ingestion-cli-contract.md` with pipeline id, steps, MIME/handler matrix, exception mapping, and Symfony allowlist. | WP01 |
| FR-002 | `IngestFileCommand` and its `AppServiceProvider::commands()` registration conform to Waaseyaa alpha.174 CLI contracts; no forbidden Symfony patterns in command body beyond documented edge. | WP02 |
| FR-003 | Ingest roster path (`IngestionHandlerRegistry`, five handlers, `CompilationPipeline` + steps) uses only Giiken domain exceptions for operator-visible failures; no Symfony exception types or array-callable dispatch in production code. | WP03 |
| FR-004 | PHPUnit arch test forbids `use Symfony\` in scoped production paths per contract allowlist; `IngestFileCommandTest` updated per plan. | WP04 |
| FR-005 | `./vendor/bin/phpunit` and `./vendor/bin/phpstan analyse src/` pass; lifecycle drift script passes if lifecycle files change. | WP04 |

## Acceptance criteria

- [ ] **`IngestFileCommand`** uses Waaseyaa-first console patterns per alpha.174; no direct **`InputInterface`** / **`OutputInterface`** usage if the framework provides a typed IO facade.
- [ ] **`AppServiceProvider::commands()`** wires the ingest command without Symfony-specific **lazy array callables** or hidden service locators in this path.
- [ ] **`IngestionHandlerRegistry`** and all handlers on the ingest-file MIME roster throw **`IngestionException`** (or documented `Throwable` mapping) — no Symfony HTTP/kernel exceptions.
- [ ] **`CompilationPipeline`** and steps surface failures as **`PipelineException`** (or step-owned Giiken types), not Symfony types; **`RuntimeException`** from step **`StepResult`** is translated at a **single** well-defined boundary.
- [ ] **`docs/specs/giiken-ingestion-cli-contract.md`** lists canonical pipeline id, step order, exception mapping, and allowed Symfony imports (if any).
- [ ] **`tests/`** include a **non-skipped** test that enforces the **no-Symfony-in-production-ingest-path** rule for owned paths.
- [ ] `./vendor/bin/phpunit`, `./vendor/bin/phpstan analyse src/`, and **`scripts/check-lifecycle-drift.sh`** (if lifecycle-impacting files change) are green.

## References (context only — do not rewrite)

- Current: `App\Console\IngestFileCommand`
- Registry / pipeline / exceptions: `App\Ingestion\IngestionHandlerRegistry`, `App\Pipeline\CompilationPipeline`, `App\Ingestion\IngestionException`, `App\Pipeline\PipelineException`
- Waaseyaa: `Waaseyaa\Foundation\ServiceProvider\Capability\HasCommandsInterface`, `Waaseyaa\Foundation\Kernel\ConsoleKernel`, `waaseyaa/*` alpha.174 dispatch/DI contracts
