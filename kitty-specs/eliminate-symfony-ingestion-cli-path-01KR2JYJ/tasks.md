# Tasks: Eliminate Symfony-shaped behavior — ingestion + compilation CLI path

**Mission:** `01KR2JYJ0PHKCN2VEHV0QQSBA5` — `eliminate-symfony-ingestion-cli-path-01KR2JYJ`  
**Spec:** [spec.md](./spec.md) · **Plan:** [plan.md](./plan.md) · **Lanes:** [lanes.json](./lanes.json)

## Work package index

| WP | Title | Lane | Status |
|----|-------|------|--------|
| [WP01](./tasks/WP01-inventory-and-contract-doc.md) | Inventory Symfony surfaces + write `docs/specs/giiken-ingestion-cli-contract.md` | `lane-contract` | planned |
| [WP02](./tasks/WP02-command-and-di.md) | Migrate `IngestFileCommand` + `AppServiceProvider` wiring to Waaseyaa CLI contract | `lane-cli` | planned |
| [WP03](./tasks/WP03-pipeline-handlers.md) | Normalize pipeline/handler exceptions and identifiers | `lane-domain` | planned |
| [WP04](./tasks/WP04-tests-and-lifecycle.md) | Arch tests + update `IngestFileCommandTest` + lifecycle drift | `lane-verify` | planned |

## Owned file boundaries (exclusive write scopes)

| WP | Owns (may edit) | Must not touch |
|----|-----------------|----------------|
| WP01 | `docs/specs/giiken-ingestion-cli-contract.md`, this `tasks/*.md`, `spec.md` typos | `src/**` except trivial comments pointing to spec |
| WP02 | `src/Console/IngestFileCommand.php`, `src/Provider/AppServiceProvider.php` (only `commands()` + imports needed for ingest command registration) | Other console commands’ bodies; HTTP controllers |
| WP03 | `src/Ingestion/IngestionHandlerRegistry.php`, `src/Ingestion/Handler/*.php` (ingest roster only), `src/Pipeline/**/*.php`, `src/Ingestion/IngestionException.php`, `src/Pipeline/PipelineException.php` | `src/Ingestion/Upload/**` |
| WP04 | `tests/**/*.php` for ingest path + new arch test, `docs/architecture/lifecycle.md` if WP02–WP03 changed runtime flow | Production `src/` except merge fixes from review |

**Ingest roster handlers (WP03):** `Csv`, `Html`, `Document`, `Markdown`, `Media` under `src/Ingestion/Handler/`.

## Dependency graph

`WP01` → `WP02`, `WP03` (contract doc gates wording)  
`WP02`, `WP03` → `WP04` (tests after implementation)

`WP02` and `WP03` may proceed in **parallel** once WP01 publishes the contract doc.

## WP01 — Inventory + contract doc

**Prompt:** [tasks/WP01-inventory-and-contract-doc.md](./tasks/WP01-inventory-and-contract-doc.md)  
**Lane:** `lane-contract`  
**Dependencies:** none

Grep Symfony-shaped patterns; publish `docs/specs/giiken-ingestion-cli-contract.md`.

## WP02 — IngestFileCommand + AppServiceProvider

**Prompt:** [tasks/WP02-command-and-di.md](./tasks/WP02-command-and-di.md)  
**Lane:** `lane-cli`  
**Dependencies:** WP01

Refactor `IngestFileCommand` and ingest command registration to Waaseyaa alpha.174 CLI contracts.

## WP03 — Pipeline + ingestion handlers

**Prompt:** [tasks/WP03-pipeline-handlers.md](./tasks/WP03-pipeline-handlers.md)  
**Lane:** `lane-domain`  
**Dependencies:** WP01

Normalize exceptions and identifiers across registry, ingest handlers, and `CompilationPipeline`.

## WP04 — Tests + lifecycle

**Prompt:** [tasks/WP04-tests-and-lifecycle.md](./tasks/WP04-tests-and-lifecycle.md)  
**Lane:** `lane-verify`  
**Dependencies:** WP02, WP03

Arch test for forbidden Symfony imports; refresh `IngestFileCommandTest`; lifecycle doc if needed.
