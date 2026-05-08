---
work_package_id: WP02
title: IngestFileCommand + AppServiceProvider
dependencies:
- WP01
requirement_refs:
- FR-002
planning_base_branch: main
merge_target_branch: main
phase: Phase 2 — CLI
assignee: ''
agent: ''
authoritative_surface: src/Console/IngestFileCommand.php
owned_files:
- src/Console/IngestFileCommand.php
- src/Provider/AppServiceProvider.php
execution_mode: code_change
---

# WP02 — IngestFileCommand + AppServiceProvider

**Mission:** `eliminate-symfony-ingestion-cli-path-01KR2JYJ`  
**Lane:** `lane-cli`

## Goal

Migrate **`App\Console\IngestFileCommand`** (and **only** the **`commands()`** registration lines needed for it in **`AppServiceProvider`**) to Waaseyaa’s alpha.174 console contract.

## Steps

1. Apply **`docs/specs/giiken-ingestion-cli-contract.md`** allowlist for CLI edge types.
2. Replace Symfony **`InputInterface`/`OutputInterface`** usage if Waaseyaa provides **`CliInput`/`CliOutput`** (or equivalent); keep operator-facing messages equivalent.
3. Keep argument names **`community-slug`** and **`file`** stable unless Waaseyaa normalizes naming (document any rename in contract doc).
4. Remove **`#[AsCommand]`** if redundant with programmatic registration.

## Done when

- [ ] `IngestFileCommand` matches foundation/cli contracts; imports in `src/Console/IngestFileCommand.php` conform to WP01 allowlist.
- [ ] `AppServiceProvider::commands()` still registers exactly three commands; only ingest command implementation changes here unless shared return type forces interface updates.
