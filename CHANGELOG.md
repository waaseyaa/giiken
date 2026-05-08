# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **CLI ingest:** `giiken:ingest:file` orchestration is now `App\Console\IngestFileCommand::run()` with a `Closure` writer — no Symfony Console types in the ingest path. `AppServiceProvider::commands()` registers a thin Symfony `Command` adapter that forwards argv and `OutputInterface` into that runner (Spec Kitty mission `eliminate-symfony-ingestion-cli-path-01KR2JYJ`).
- **Markdown ingestion:** Frontmatter is parsed with a small PHP YAML subset instead of `Symfony\Component\Yaml\Yaml`, keeping the ingest CLI/handler path free of `use Symfony\` imports.
- **Docs:** `docs/specs/giiken-ingestion-cli-contract.md` and `docs/architecture/lifecycle.md` updated for the split between the console adapter and Symfony-free ingest logic.
- **Tests:** `IngestFileCommandTest` targets `run()` directly; `IngestionCliPathNoSymfonyTest` forbids `use Symfony\` in listed production sources for the ingest → pipeline path.
