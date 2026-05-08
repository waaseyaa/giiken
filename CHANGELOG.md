# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Playwright smoke:** `npm run test:e2e` boots an ephemeral SQLite DB (`/tmp/giiken-playwright-smoke.sqlite`), runs `migrate` + `giiken:seed:test-community`, serves `public/` on port 9323, and asserts `GET /` and `GET /test-community` return Inertia payloads.

### Changed

- **Waaseyaa native CLI:** Giiken registers **`giiken:ingest:file`** and **`giiken:seed:test-community`** via `AppServiceProvider::nativeCommands()` (**`HasNativeCommandsInterface`**, `CommandDefinition`, `GiikenIngestFileHandler`, `GiikenSeedTestCommunityHandler`) with Waaseyaa **`Waaseyaa\CLI\CliKernel`** (no Symfony Console in the framework runtime after waaseyaa `native-cli-kernel-01KR2NR7`). **`search:reindex`** is framework-owned only — removed Giiken’s duplicate wrapper.
- **CLI ingest:** Orchestration remains **`IngestFileCommand::run(..., Closure $writeln)`** with zero Symfony types in domain/ingest/pipeline code (Spec Kitty mission `eliminate-symfony-ingestion-cli-path-01KR2JYJ`).
- **Markdown ingestion:** Frontmatter is parsed with a small PHP YAML subset instead of `Symfony\Component\Yaml\Yaml`, keeping the ingest CLI/handler path free of `use Symfony\` imports.
- **Docs:** `docs/specs/giiken-ingestion-cli-contract.md`, lifecycle, and `CLAUDE.md` updated for **`HasNativeCommandsInterface`** and handler-based registration.
- **Tests:** `IngestFileCommandTest` targets `run()` directly; `IngestionCliPathNoSymfonyTest` forbids `use Symfony\` in listed production sources for the ingest → pipeline path; **`AppServiceProviderTest`** asserts **`HasNativeCommandsInterface`**.
