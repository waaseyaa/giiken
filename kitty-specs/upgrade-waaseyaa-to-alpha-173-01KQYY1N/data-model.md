# Data Model — Upgrade Waaseyaa to alpha.173

**Mission:** `upgrade-waaseyaa-to-alpha-173-01KQYY1N`
**Date:** 2026-05-06

## Status

**No new entities introduced. No fields added or removed. No relationships changed. No state-machine transitions altered.**

This mission is a framework-version upgrade. The data model is preserved across the migration; what changes is how the existing data model is *registered* with the framework's `EntityTypeManager`.

## Existing entities (unchanged in scope, registration pattern migrated in Phase 3)

| Entity type ID | Class | Registered in |
|---|---|---|
| `community` | `App\Entity\Community\Community` | `App\Provider\AppServiceProvider` (or `EntitiesProvider`) |
| `knowledge_item` | `App\Entity\KnowledgeItem\KnowledgeItem` | `App\Provider\AppServiceProvider` |
| `wiki_lint_report` | `App\Wiki\WikiLintReport` | `App\Provider\AppServiceProvider` |

### Registration migration (Phase 3 of plan.md)

**Before (alpha.145 pattern):**

```php
// in EntitiesProvider or similar
$entityTypeManager->register(new EntityType(
    id: 'knowledge_item',
    class: KnowledgeItem::class,
    fieldDefinitions: [
        // explicit FieldDefinition instances
    ],
));
```

**After (alpha.162+ pattern, attribute-first):**

```php
// on the entity class itself
#[ContentEntityType('knowledge_item', label: 'Knowledge Item', description: '...')]
class KnowledgeItem extends ContentEntityBase
{
    #[Field(required: true)]
    public string $title = '';

    #[Field(type: 'enum', settings: ['enum_class' => KnowledgeType::class])]
    public KnowledgeType $type = KnowledgeType::Cultural;

    // ...
}

// in EntitiesProvider
$entityTypeManager->register(EntityType::fromClass(KnowledgeItem::class));
```

Field type inference (`FieldTypeInferrer`, alpha.162) means most properties don't need explicit `type:` arguments; the inferrer maps PHP types to canonical field types. Backed enums are inferred to `type: 'enum'` automatically (alpha.162 `EnumItem` plugin closes the previous transitional `string + enum_class` bridge).

### What is preserved

- All existing fields on `Community`, `KnowledgeItem`, and `WikiLintReport`.
- All access policies (`KnowledgeItemAccessPolicy` + the role × tier matrix in `CommunityRole` × `AccessTier`).
- All repository interfaces (`CommunityRepositoryInterface`, `KnowledgeItemRepositoryInterface`).
- All value objects under `Entity/KnowledgeItem/Source/` (`Attribution`, `CopyrightStatus`, `Rights`, etc.).
- All migration files under `migrations/` — the schema is unchanged.
- The `WikiSchema` value object on `Community` and the `SovereigntyProfile` companion.

### Field-storage semantics (alpha.150 enum)

When Phase 3 introduces `#[Field]` attributes, fields without a dedicated database column must declare `stored: FieldStorage::Data` to land in the `_data` JSON blob rather than expecting a column. Phase 3 confirms each entity's storage hint matches the existing schema; for the `knowledge_item` table, the existing migration columns (`title`, `type`, `body`, etc.) determine which fields are `Column` vs `Data`.

## Out of scope for this mission

- Adding new fields to existing entities.
- Adding new entities.
- Changing access tiers, role hierarchy, or policy decisions.
- Changing the wiki-lint check set or its report shape.
- Schema migrations beyond what attribute-first registration may emit (which should be none — registration is a runtime concern, not a schema concern).
