# Giiken: ingestion + compilation CLI contract

**Status:** active (mission `eliminate-symfony-ingestion-cli-path-01KR2JYJ`)  
**Scope:** `giiken:ingest:file` → `IngestionHandlerRegistry` → file handlers → `CompilationPipeline` → `KnowledgeItem` persistence  
**Non-scope:** HTTP upload (`ManagementController::ingestUpload` / `UploadedFile`), other console commands, NorthCloud sync

## Command

| Property | Value |
|----------|--------|
| Name | `giiken:ingest:file` |
| Arguments | `community_slug`, `file` (positional strings; Waaseyaa-native names use underscores) |
| Success exit | `0` (`App\Console\IngestFileCommand::EXIT_SUCCESS`) |
| Failure exit | `1` (`App\Console\IngestFileCommand::EXIT_FAILURE`) |

**Registration:** `App\Provider\AppServiceProvider::nativeCommands()` yields a **CommandDefinition** for `giiken:ingest:file` with handler **GiikenIngestFileHandler::execute** (Waaseyaa **HasNativeCommandsInterface**). The handler forwards to **IngestFileCommand::run**. Kernel: `Waaseyaa\CLI\CliKernel` (Symfony Console absent from Waaseyaa runtime after waaseyaa/native-cli-kernel mission).

**Output:** Plain text lines via **`CliIO::writeln` / stderr via `CliIO::error`**.

## Pipeline identity

| Property | Value |
|----------|--------|
| `PipelineContext::pipelineId` | `compilation` |
| Step order | `TranscribeStep` → `ClassifyStep` → `StructureStep` → `LinkStep` → `EmbedStep` |

Step **description strings** passed to `PipelineException::fromStep()` must match each step’s **`describe()`** return value (stable for operator messages).

## Handler selection

Handlers implement **`App\Ingestion\FileIngestionHandlerInterface`**. The registry selects the **first** registered handler where **`supports($mimeType)`** is true. MIME for CLI is determined by **`IngestFileCommand::detectMimeType()`** (extension map, then `finfo`).

Ingest-roster classes (Giiken):

- `CsvIngestionHandler`
- `HtmlIngestionHandler`
- `DocumentIngestionHandler`
- `MarkdownIngestionHandler`
- `MediaIngestionHandler`

**Markdown frontmatter:** `MarkdownIngestionHandler` parses a minimal YAML subset (scalars, quoted strings, indented `-` lists) in PHP — no `Symfony\Component\Yaml` — so this path stays free of `use Symfony\`.

## Exception mapping

| Layer | Thrown type | Notes |
|-------|-------------|--------|
| Registry / handlers | `App\Ingestion\IngestionException` | Extends `RuntimeException` — acceptable domain base |
| Pipeline orchestration | `App\Pipeline\PipelineException` | Wraps step failures; **`$previous`** may be `RuntimeException` from `StepResult` |
| Step internals | — | Must not throw Symfony component exceptions into this path |

**Forbidden** in production code under this path: any `Symfony\Component\*Exception*`, `Symfony\Contracts\...\Exception*`, or Symfony HTTP/kernel exceptions.

## Symfony `use` statements (production)

**Enforced** by `tests/Unit/Architecture/IngestionCliPathNoSymfonyTest.php` for these paths:

- `src/Console/IngestFileCommand.php`
- `src/Console/Handler/GiikenIngestFileHandler.php`
- `src/Console/Handler/GiikenSeedTestCommunityHandler.php`
- `src/Ingestion/IngestionHandlerRegistry.php`
- `src/Ingestion/IngestionException.php`
- `src/Ingestion/Handler/{Csv,Html,Document,Markdown,Media}IngestionHandler.php`
- `src/Pipeline/**/*.php` (as listed in the test provider)

**AppServiceProvider:** must not register Giiken commands via Symfony Console; **`HasNativeCommandsInterface` only**.

## Tests

- **`IngestFileCommandTest`** exercises `IngestFileCommand::run()` failure paths with a capturing writer closure.
- **`IngestionCliPathNoSymfonyTest`** asserts zero `use Symfony\...` lines in guarded production files.

## WP01 inventory (Symfony-shaped, resolved)

| Location | Before | After |
|----------|--------|--------|
| `IngestFileCommand.php` | Extended Symfony `Command`, `InputInterface` / `OutputInterface` | Plain service; `run(..., Closure $writeln)` |
| `MarkdownIngestionHandler.php` | `Symfony\Component\Yaml\Yaml` | Inline minimal YAML frontmatter parser |
| `AppServiceProvider.php` | Symfony `HasCommandsInterface` shim | **`HasNativeCommandsInterface`** → **CommandDefinition** + DI for handlers |

No Symfony imports remain in pipeline package code or ingest roster handlers.

## Git note (2026-05 follow-up after Waaseyaa native-cli-kernel)

- **`search:reindex`** remains available from **`Waaseyaa\CLI`** (`SearchReindexHandler`); Giiken no longer duplicates it.

## Changelog

- 2026-05-08 — Initial draft for Spec Kitty mission WP01.
- 2026-05-08 — Mission implementation: Symfony-free orchestration, markdown frontmatter parser, arch test, lifecycle note.
- 2026-05-08 — Follow-up: **`HasNativeCommandsInterface`**, `GiikenIngestFileHandler`, `GiikenSeedTestCommunityHandler`; duplicate Giiken **`search:reindex`** removed (framework command only).
