# EntityRepositoryFactory Design — waaseyaa/framework#1128

**Date:** 2026-04-06
**Target repo:** `waaseyaa/framework` (package `packages/entity-storage`)
**Upstream issue:** waaseyaa/framework#1128
**Downstream unblocks:** waaseyaa/giiken#42

## Problem

`Waaseyaa\EntityStorage\EntityRepository` is the framework's only `EntityRepositoryInterface` implementation. Its constructor takes 3–7 dependencies:

```php
public function __construct(
    private readonly EntityTypeInterface $entityType,
    private readonly EntityStorageDriverInterface $driver,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ?RevisionableStorageDriver $revisionDriver = null,
    private readonly ?DatabaseInterface $database = null,
    ?EntityEventFactoryInterface $eventFactory = null,
    private readonly ?EntityValidator $validator = null,
) { ... }
```

No production code in the framework calls `new EntityRepository(...)` — only tests. There is no `EntityTypeManager::getRepository()` accessor, and no dedicated factory class. Every consumer that wants a per-entity-type domain repository (e.g. giiken's `CommunityRepository` wrapping an `EntityRepositoryInterface`) must either:

1. Re-assemble all the dependencies inside its own service provider (DRY violation, duplicated plumbing across consumers), or
2. Drop down to `EntityTypeManager::getStorage()` and bypass the repository layer entirely (losing events, validation, revisions).

For giiken this is the immediate blocker on waaseyaa/giiken#42: `GiikenServiceProvider::register()` cannot cleanly bind `CommunityRepositoryInterface` or `KnowledgeItemRepositoryInterface` without first deciding how to construct the underlying `EntityRepositoryInterface`.

## Approach

Add a dedicated factory — `Waaseyaa\EntityStorage\EntityRepositoryFactory` — in the `entity-storage` package, plus the first service provider for that package (`EntityStorageServiceProvider`) to register the factory as a container singleton.

### Why this package, not `EntityTypeManager`

The issue body suggests `EntityTypeManager::getRepository()`. Rejected because:

- `EntityTypeManager` lives in `packages/entity`.
- `EntityRepository` lives in `packages/entity-storage`.
- Adding `getRepository()` to `EntityTypeManager` would reverse the current dependency direction (`entity` → `entity-storage`).
- `EntityTypeManager::getStorage()` returns an `EntityStorageInterface`, while `EntityRepository` takes an `EntityStorageDriverInterface` — different abstractions. A shim on `EntityTypeManager` would have to bridge that mismatch.

Putting the factory in `entity-storage` keeps package boundaries clean and co-locates the factory with the class it builds. `EntityStorageFactory` already lives in this package as precedent.

## Architecture

### New files

1. `packages/entity-storage/src/EntityRepositoryFactory.php` — memoizing factory
2. `packages/entity-storage/src/EntityStorageServiceProvider.php` — registers the factory (first service provider in this package)
3. `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php` — unit tests

### Modified files

4. `docs/specs/entity-system.md` — document the factory in the existing entity-system spec
5. `packages/entity-storage/composer.json` — add service-provider discovery metadata if the framework uses composer `extra` for that (to be verified during implementation)

No changes to `packages/entity`, `EntityTypeManager`, `EntityRepository`, or any driver class. Existing tests must remain green unchanged.

## Components

### `EntityRepositoryFactory`

```php
namespace Waaseyaa\EntityStorage;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEventFactoryInterface;
use Waaseyaa\EntityStorage\Driver\EntityStorageDriverInterface;

/**
 * Builds and memoizes EntityRepository instances by entity type id.
 */
final class EntityRepositoryFactory
{
    /** @var array<string, EntityRepositoryInterface> */
    private array $repositories = [];

    /**
     * @param Closure(EntityTypeInterface): EntityStorageDriverInterface $driverFactory
     */
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Closure $driverFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?DatabaseInterface $database = null,
        private readonly ?EntityValidator $validator = null,
        private readonly ?RevisionableStorageDriver $revisionDriver = null,
        private readonly ?EntityEventFactoryInterface $eventFactory = null,
    ) {}

    public function get(string $entityTypeId): EntityRepositoryInterface
    {
        if (isset($this->repositories[$entityTypeId])) {
            return $this->repositories[$entityTypeId];
        }

        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $driver = ($this->driverFactory)($entityType);

        return $this->repositories[$entityTypeId] = new EntityRepository(
            $entityType,
            $driver,
            $this->eventDispatcher,
            $this->revisionDriver,
            $this->database,
            $this->eventFactory,
            $this->validator,
        );
    }

    public function has(string $entityTypeId): bool
    {
        return $this->entityTypeManager->hasDefinition($entityTypeId);
    }
}
```

**Design notes:**

- **Driver factory closure, not a single driver.** `SqlStorageDriver` is per-entity-type — its constructor takes an `$idKey` derived from `EntityTypeInterface::getKeys()`. A shared instance cannot serve multiple types. Closure pattern mirrors `EntityTypeManager::__construct(..., ?Closure $storageFactory = null)` for consistency.
- **Memoization** matches `EntityTypeManager::getStorage()` and `EntityStorageFactory::getStorage()`.
- **Definition lookup delegates** to `EntityTypeManager` — no parallel registry.
- **All optional repository deps** (validator, revision driver, database, event factory) are forwarded through so no repository functionality is lost.
- **Method name `get()`**, not `getRepository()` — the class name already says "Repository"; `$factory->get('community')` reads fine.

### `EntityStorageServiceProvider`

```php
namespace Waaseyaa\EntityStorage;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Driver\EntityStorageDriverInterface;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class EntityStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(EntityRepositoryFactory::class, function ($container) {
            return new EntityRepositoryFactory(
                entityTypeManager: $container->get(EntityTypeManager::class),
                driverFactory: $this->defaultDriverFactory($container),
                eventDispatcher: $container->get(EventDispatcherInterface::class),
                database: $container->has(DatabaseInterface::class)
                    ? $container->get(DatabaseInterface::class)
                    : null,
                validator: $container->has(EntityValidator::class)
                    ? $container->get(EntityValidator::class)
                    : null,
            );
        });
    }

    private function defaultDriverFactory($container): Closure
    {
        return function (EntityTypeInterface $entityType) use ($container): EntityStorageDriverInterface {
            $keys = $entityType->getKeys();
            $idKey = $keys['id'] ?? 'id';

            return new SqlStorageDriver(
                connectionResolver: $container->get(ConnectionResolverInterface::class),
                idKey: $idKey,
                communityScope: $container->has(CommunityScope::class)
                    ? $container->get(CommunityScope::class)
                    : null,
            );
        };
    }
}
```

**Design notes:**

- **Default driver is `SqlStorageDriver`** — the production path. Tests and in-memory scenarios override the factory binding in their own provider.
- **Optional deps use `$container->has()` guards** so the provider works in minimal boot scenarios.
- **Exact `ServiceProvider` base class API** (method names, discovery mechanism) will be verified against `RelationshipServiceProvider` / `MediaServiceProvider` during implementation. The sketch above matches the shape of `RelationshipServiceProvider`.

## Tests

**File:** `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

Uses `InMemoryStorageDriver` (already in the package, stateless, no database setup).

| # | Case | Verifies |
|---|---|---|
| 1 | `get_returns_entity_repository_for_registered_type` | Factory returns a non-null `EntityRepositoryInterface` |
| 2 | `get_memoizes_same_instance` | Two calls with the same id return the same object |
| 3 | `get_builds_distinct_instances_for_different_types` | Different ids produce different objects |
| 4 | `get_throws_for_unknown_entity_type` | Unknown id surfaces `EntityTypeManager`'s existing `InvalidArgumentException` |
| 5 | `returned_repository_can_save_and_load_entity` | End-to-end via `InMemoryStorageDriver` proves the closure, event dispatcher, and memoization all wire correctly |
| 6 | `driver_factory_receives_entity_type` | Spy closure captures arg to confirm the correct `EntityTypeInterface` is passed |
| 7 | `has_delegates_to_entity_type_manager` | Sanity check on the `has()` shim |

**Bootstrap pattern:**

```php
$manager = new EntityTypeManager(new NullEventDispatcher());
$manager->registerEntityType($testType);

$factory = new EntityRepositoryFactory(
    entityTypeManager: $manager,
    driverFactory: fn() => new InMemoryStorageDriver(),
    eventDispatcher: new NullEventDispatcher(),
);
```

**Not covered in this file** (already covered by `EntityRepositoryTest` / `EntityRepositoryRevisionTest`): repository semantics, `SqlStorageDriver`, `EntityValidator`, revisions, translations.

## Verification gates (definition of done)

1. `./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php` — all 7 cases green
2. `./vendor/bin/phpunit packages/entity-storage` — full package suite green, no regressions
3. `./vendor/bin/phpstan analyse packages/entity-storage` — no new errors at the existing level
4. Spec drift detector on `docs/specs/entity-system.md` passes
5. Downstream probe from giiken: a throwaway script calls `$container->get(EntityRepositoryFactory::class)->get('community')` against a sqlite test database and receives a working `EntityRepositoryInterface`. Verification only — the actual giiken wiring in #42 is a separate PR.

## Documentation update

Add a section to `docs/specs/entity-system.md` titled "Wiring per-entity-type repositories" that:

- Describes the `EntityRepositoryFactory` contract in one paragraph
- Shows the canonical consumer snippet:
  ```php
  $this->singleton(CommunityRepositoryInterface::class, fn($c) =>
      new CommunityRepository(
          $c->get(EntityRepositoryFactory::class)->get('community')
      )
  );
  ```
- Notes that the default driver is `SqlStorageDriver` and can be swapped by re-binding `EntityRepositoryFactory` in the consumer's own service provider
- If an existing `getStorage` vs repository comparison table exists, adds a row for the new factory

## Out of scope

- `EntityTypeManager::getRepository()` convenience shim — would force package dep reversal
- Making `EntityStorageFactory` and `EntityRepositoryFactory` aware of each other — they build different things (storage vs repository) and stay independent
- Fixing waaseyaa/framework#1126 (hook discipline) — separate blocker
- Landing alpha.108 — release orchestration is downstream of this PR
- Wiring giiken's `CommunityRepository` / `KnowledgeItemRepository` bindings (waaseyaa/giiken#42) — consumes this work, separate PR
- Any change to `EntityStorageDriverInterface` or the driver implementations

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| `ServiceProvider` base class API differs from the sketch | Verify against `RelationshipServiceProvider` / `MediaServiceProvider` before writing the impl |
| Container discovery requires composer `extra` metadata not yet inspected | Check one existing package's `composer.json` in the first implementation task |
| `EntityValidator` namespace is wrong | Grep for the class before importing |
| `SqlStorageDriver` default needs additional deps in some boot configurations | A failing test surfaces it; extend the default driver factory closure accordingly |
| `CommunityScope` symbol lives in a different package than assumed | Grep before importing; if outside `entity-storage`, wrap with `has()` guard |

## Success criteria

- PR merges green into `waaseyaa/framework` main
- Downstream probe from giiken returns a working repository via the factory without hand-assembling dependencies
- waaseyaa/framework#1128 is closed
- `GiikenServiceProvider::register()` can now cleanly bind `CommunityRepositoryInterface` and `KnowledgeItemRepositoryInterface` in a follow-up PR (#42), unblocking the boot-to-browser path

## Execution order

1. This PR against `waaseyaa/framework` — lands `EntityRepositoryFactory`, `EntityStorageServiceProvider`, tests, spec update
2. Release a new framework alpha (coordinated with framework#1126 hook discipline work, if that's a prerequisite for cutting releases)
3. Bump `waaseyaa/*` in `waaseyaa/giiken` to the new alpha
4. Separate giiken PR for #42 that binds the domain repositories using the new factory
