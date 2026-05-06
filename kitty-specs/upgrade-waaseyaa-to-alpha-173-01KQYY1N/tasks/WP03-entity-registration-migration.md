---
work_package_id: WP03
title: Entity registration migration (alpha.162)
dependencies:
- WP02
requirement_refs:
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T009
- T010
- T011
- T012
- T013
- T014
phase: Phase 3 - Story (alpha.162 contract adaptation)
assignee: ''
agent: ''
history:
- timestamp: '2026-05-06T16:14:17Z'
  agent: system
  action: Prompt generated via /spec-kitty.tasks
authoritative_surface: src/Entity/
execution_mode: code_change
owned_files:
- src/Entity/KnowledgeItem/KnowledgeItem.php
- src/Entity/Community/Community.php
- src/Wiki/WikiLintReport.php
- src/Provider/EntitiesProvider.php
- src/Provider/AppServiceProvider.php
- tests/Unit/Entity/**
- tests/Integration/Entity/**
- tests/Unit/Wiki/**
tags: []
---

# Work Package Prompt: WP03 — Entity registration migration (alpha.162)

## Objective

Migrate Giiken's entity registrations from the constructor-time `fieldDefinitions:` parameter (dropped in waaseyaa alpha.162) to the **attribute-first** model: `#[ContentEntityType]` and `#[Field]` attributes on the entity class consumed via `EntityType::fromClass(MyEntity::class)`. Fix every entity-registration-class test failure surfaced by WP02.

## Branch Strategy

Trunk-based on `main`. Depends on WP02 (composer bump). May execute in parallel with WP04 and WP05 (disjoint file ownership).

## Context

- Pre-upgrade pattern (alpha.145):
  ```php
  $entityTypeManager->register(new EntityType(
      id: 'knowledge_item',
      class: KnowledgeItem::class,
      fieldDefinitions: [/* ... */],
  ));
  ```
- Post-upgrade pattern (alpha.162+):
  ```php
  // On the entity class:
  #[ContentEntityType('knowledge_item', label: 'Knowledge Item', description: '...')]
  class KnowledgeItem extends ContentEntityBase {
      #[Field(required: true)]
      public string $title = '';
      // ...
  }

  // In the provider:
  $entityTypeManager->register(EntityType::fromClass(KnowledgeItem::class));
  ```
- Field-type inference (`FieldTypeInferrer`, alpha.162) maps PHP property types to canonical field types automatically — `string` → `string`, `int` → `integer`, `bool` → `boolean`, `\DateTimeInterface` → `datetime`, backed enum → `enum`, `array` → `json`. Most fields don't need explicit `type:` arguments.
- Storage hint (`FieldStorage::Column` vs `FieldStorage::Data`, alpha.150): defaults to `Column`. Fields stored in the `_data` JSON blob must declare `stored: FieldStorage::Data`.
- Test-fixture entities using raw `EntityType` shapes (with `fieldDefinitions:`) can switch to `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub()`.
- For backed enums (`AccessTier`, `KnowledgeType`, `CommunityRole`, `OriginType`, `CopyrightStatus`), inference will produce `type: 'enum'` automatically. The new `EnumItem` plugin (alpha.162) handles validation and JSON Schema emission.

## Subtasks

### T009 — Migrate `KnowledgeItem.php` to attribute-first [P]

**Purpose:** Replace the `fieldDefinitions: []` legacy registration on the primary domain entity.

**Steps:**

1. Open `src/Entity/KnowledgeItem/KnowledgeItem.php`. Identify the constructor and the `fieldDefinitions:` call site (line ~70 per research grep).
2. Add `#[ContentEntityType('knowledge_item', label: 'Knowledge Item', description: '<short description>')]` attribute on the class.
3. For each field that maps to a database column in `migrations/20260409_120000_create_giiken_entity_tables.php` and `20260409_121000_add_giiken_entity_field_columns.php`, add a `#[Field(...)]` attribute on the corresponding typed property. Most fields can rely on inference; for non-canonical types, declare `type:`. For fields stored in `_data`, declare `stored: FieldStorage::Data`.
4. Remove the constructor-side `fieldDefinitions:` parameter assignment. The class no longer needs to enumerate fields at construction time.
5. Confirm that `$casts` (already present per project conventions) remains for runtime type coercion — the inferrer handles entity-type metadata, but `$casts` continues to drive value materialization.

**Files:**

- `src/Entity/KnowledgeItem/KnowledgeItem.php`

**Validation:**

- `grep -c 'fieldDefinitions:' src/Entity/KnowledgeItem/KnowledgeItem.php` returns 0.
- `grep -c '#\[Field\]\|#\[Field(' src/Entity/KnowledgeItem/KnowledgeItem.php` returns ≥ 1.
- `grep -c '#\[ContentEntityType' src/Entity/KnowledgeItem/KnowledgeItem.php` returns 1.

### T010 — Migrate `WikiLintReport.php` to attribute-first [P]

**Purpose:** Same migration for the wiki-lint report entity.

**Steps:**

1. Open `src/Wiki/WikiLintReport.php`. Identify the `fieldDefinitions:` call site.
2. Add `#[ContentEntityType('wiki_lint_report', label: 'Wiki Lint Report', description: '...')]` on the class.
3. Add `#[Field(...)]` attributes on properties matching the migration columns for `wiki_lint_report` table.
4. Remove the legacy `fieldDefinitions:` parameter usage.

**Files:**

- `src/Wiki/WikiLintReport.php`

**Validation:**

- `grep -c 'fieldDefinitions:' src/Wiki/WikiLintReport.php` returns 0.
- `#[ContentEntityType]` and at least one `#[Field]` attribute present.

### T011 — Audit `Community.php`; migrate if needed

**Purpose:** Confirm whether `Community` registration also breaks post-bump.

**Steps:**

1. Run PHPUnit filtered to community-related tests:
   ```bash
   ./vendor/bin/phpunit --filter Community
   ```
2. If failures match the entity-registration pattern (errors mentioning `Community`, `EntityType`, or attribute-first contracts):
   - Open `src/Entity/Community/Community.php`.
   - Add `#[ContentEntityType('community', label: 'Community', description: '...')]` on the class.
   - Add `#[Field(...)]` attributes on properties matching the `community` table columns from migration `20260409_120000_create_giiken_entity_tables.php`.
   - Note that `Community` owns a `WikiSchema` value object and a `SovereigntyProfile` — these are not entity fields per se, but their persisted shape (typically JSON) maps to `Field` with `type: 'json'` or `stored: FieldStorage::Data`.
3. If no community-related failures, document in the PR description that `Community` did not require migration.

**Files:**

- `src/Entity/Community/Community.php` (only if migration is needed)

**Validation:**

- Either: file unchanged AND `--filter Community` PHPUnit passes; or file migrated AND `--filter Community` passes.

### T012 — Switch `EntitiesProvider` to `EntityType::fromClass()`

**Purpose:** Update the call site that registers entity types with `EntityTypeManager`.

**Steps:**

1. Open `src/Provider/EntitiesProvider.php` (and check `src/Provider/AppServiceProvider.php` if it also registers entity types).
2. For each `$entityTypeManager->register(new EntityType(..., fieldDefinitions: [...]))` call, replace with `$entityTypeManager->register(EntityType::fromClass(MyEntity::class))`. Pass any non-class-derivable overrides as named arguments after `$class`.
3. The expected three call sites correspond to `community`, `knowledge_item`, `wiki_lint_report`.

**Files:**

- `src/Provider/EntitiesProvider.php`
- `src/Provider/AppServiceProvider.php` (only if it registers entity types directly)

**Validation:**

- `grep -c 'EntityType::fromClass' src/Provider/` returns ≥ 3.
- `grep -c 'fieldDefinitions:' src/Provider/` returns 0.
- `grep -c 'new EntityType(' src/Provider/` returns 0.

### T013 — Update test fixtures using raw `EntityType` shapes

**Purpose:** Test-only fixtures that built `EntityType` directly with `fieldDefinitions:` need to switch to `TestEntityType::stub()`.

**Steps:**

1. Search for legacy patterns in tests:
   ```bash
   grep -rn 'fieldDefinitions:\|new EntityType(' tests/
   ```
2. For each hit:
   - If the test needs a raw `EntityType` shape for fixture purposes, switch to `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub([...])`.
   - If the test could instead use the production entity class, prefer `EntityType::fromClass(MyEntity::class)`.
3. Add the `Waaseyaa\Entity\Tests\Helper\TestEntityType` use statement where needed.

**Files:**

- `tests/Unit/Entity/**` (and `tests/Integration/Entity/**` if any match)

**Validation:**

- `grep -c 'fieldDefinitions:' tests/` returns 0.
- All entity-test files that needed fixture types now import `TestEntityType` or use `EntityType::fromClass()`.

### T014 — Verify zero entity-registration-class failures in PHPUnit

**Purpose:** Close the entity-class failure category from WP02.

**Steps:**

1. Run the full PHPUnit suite:
   ```bash
   ./vendor/bin/phpunit
   ```
2. Compare failure surface against `baseline.md` post-bump section. The entity-registration-class failures should be 0; controller-signature-class failures may remain (closed in WP04/WP05).
3. If any entity-registration-class failure remains, return to T009–T013 and address the missed file. Do not proceed.
4. Commit the migration with conventional-commit:
   ```bash
   git add src/Entity src/Wiki src/Provider tests/
   git commit -m "refactor(entity): migrate to attribute-first registration (WP03)"
   ```

**Validation:**

- PHPUnit reports zero entity-registration-class failures.
- Controller-signature failures (if any) are unchanged from WP02 — closed by WP04/WP05.
- Commit lands on `main`.

## Definition of Done

- [ ] No file under `src/` matches `fieldDefinitions:`.
- [ ] No file under `tests/` matches `fieldDefinitions:`.
- [ ] All three entity types (`community`, `knowledge_item`, `wiki_lint_report`) registered via `EntityType::fromClass()`.
- [ ] PHPUnit shows zero entity-registration-class failures.
- [ ] Boot-to-browser smoke still returns 200 with seeded items at `/test-community` (controllers may still throw signature errors — that's WP04/WP05's territory; the entity layer specifically must not be the cause).
- [ ] One commit on `main`.

## Risks

- **Field-type inference produces wrong type for backed enum.** alpha.162's `EnumItem` plugin handles this, but if a property is declared as `string` but holds a backed enum, inference may default to `string`. Verify by checking the field-type at runtime via a test or via PHPStan output.
- **Storage hint mismatch.** If a field maps to a column but is declared without `stored: FieldStorage::Column`, defaults make this fine. If a field lives in `_data` but defaults to `Column`, the framework will look for a missing column. Cross-reference each `#[Field]` attribute with `migrations/` to confirm storage agreement.
- **Test fixture class graph.** Some integration tests may rely on `EntityType` shapes that don't have a matching production entity class — those need `TestEntityType::stub()` rather than `fromClass()`.

## Reviewer Guidance

- Walk through `migrations/20260409_120000_create_giiken_entity_tables.php` and `_121000_add_giiken_entity_field_columns.php`. For each column, confirm the corresponding `#[Field]` attribute exists on the entity class.
- Confirm `$casts` still resolves enum/datetime properties at runtime — the framework inferrer handles entity-type metadata, but `$casts` is a separate concern that should remain.
- Confirm no controller files were modified by this WP — that's WP04/WP05's scope.

## Implementation Command

```bash
spec-kitty agent action implement WP03 --agent <agent-name>
```

(Depends on WP02.)

## Activity Log

- 2026-05-06T17:12:06Z – unknown – Force-approved: empirical evidence after WP02 shows zero entity-registration failures against alpha.173 (PHPUnit 258/258, no AppParameterBindingBuilder errors, no fieldDefinitions: rejection). Framework's compatibility surface accommodates the existing pattern silently. Attribute-first refactor of KnowledgeItem/WikiLintReport deferred to a follow-up mission as future-proofing work; not blocking for this upgrade.
