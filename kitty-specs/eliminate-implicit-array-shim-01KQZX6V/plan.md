# Implementation Plan: Eliminate alpha.173 Implicit-Array Shim

**Mission**: `eliminate-implicit-array-shim-01KQZX6V` (mid8 `01KQZX6V`)  
**Spec**: [spec.md](./spec.md)  
**Status**: Implementation executed in Cursor (2026-05-07) after the Claude Code plan agent hit usage limits; the prior template placeholder was never filled by `/spec-kitty.plan`.

## Summary

1. **Controllers** — Add `#[MapRoute]` / `#[MapQuery]` (`Waaseyaa\SSR\Attribute\*`) to every SSR dispatch method that used unannotated `array $params` / `array $query` (six controllers, nineteen methods). Private helpers that accept `$params`/`$query` as ordinary PHP parameters are unchanged; they are not dispatcher entry points.
2. **Entities** — Add `#[ContentEntityType]` + `#[ContentEntityKeys]` on `Community`, `KnowledgeItem`, and `WikiLintReport`. Remove the fourth `fieldDefinitions` constructor argument; call `parent::__construct` with three arguments only. Register types via `EntityType::fromClass()` in `EntitiesProvider`.
3. **Composer local** — Broken path-repo in `composer.local.json` (deleted worktree) was corrected locally to `../waaseyaa/packages/*` so `composer` commands work. `alpha.174` is not published on Packagist yet; published constraints remain `^0.1.0-alpha.173`.

## Verification

- `./vendor/bin/phpunit` — 258 tests, 807 assertions, exit 0  
- `./vendor/bin/phpstan analyse src/` — no errors  
- `npm run test:js` — 39 tests, exit 0  
- `docs/architecture/lifecycle.md` updated for entity registration + controller dispatch contract

## Housekeeping (2026-05-08)

- [`occurrence_map.yaml`](./occurrence_map.yaml) — filed and validated with `specify_cli.bulk_edit.occurrence_map.validate_against_schema` (DIRECTIVE_035 / C-006).
- [`tasks.md`](./tasks.md) — retroactive task index (all complete).
- [`artifacts/migrations-resolved.md`](./artifacts/migrations-resolved.md) — inventory vs. migration-notes / SC-3.
