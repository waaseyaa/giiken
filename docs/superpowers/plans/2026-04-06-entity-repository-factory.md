# EntityRepositoryFactory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land `Waaseyaa\EntityStorage\EntityRepositoryFactory` + `EntityStorageServiceProvider` in `waaseyaa/framework` so downstream consumers (starting with `waaseyaa/giiken`) can bind per-entity-type domain repositories without hand-assembling the 3–7 `EntityRepository` dependencies. Closes waaseyaa/framework#1128. Unblocks waaseyaa/giiken#42.

**Architecture:** A new `EntityRepositoryFactory` class (memoizing, TDD'd against `InMemoryStorageDriver`) lives in `packages/entity-storage`. A new `EntityStorageServiceProvider` in the same package registers the factory as a singleton plus a default `ConnectionResolverInterface → SingleConnectionResolver` binding so `SqlStorageDriver` is usable out of the box. The package currently has no service provider, so this is additive. `EntityTypeManager` in `packages/entity` is untouched — package dependency direction stays clean.

**Tech Stack:** PHP 8.4+, PHPUnit 10.5, `Waaseyaa\Foundation\ServiceProvider\ServiceProvider` base class, composer `extra.waaseyaa.providers` discovery.

---

## Working directory

This plan runs in a dedicated git worktree off the framework repo:

- Framework repo root: `/home/fsd42/dev/waaseyaa`
- Worktree path (created in Task 1): `/home/fsd42/dev/waaseyaa-sync-entity-repository-factory`
- Branch: `feature/1128-entity-repository-factory`

All file paths below are relative to the worktree root.

## File structure

| File | Action | Responsibility |
|---|---|---|
| `packages/entity-storage/src/EntityRepositoryFactory.php` | **Create** | Memoizing factory for `EntityRepositoryInterface` instances keyed by entity type id. Delegates definition lookup to `EntityTypeManager`. Driver construction is delegated to an injected closure. |
| `packages/entity-storage/src/EntityStorageServiceProvider.php` | **Create** | First service provider in this package. Registers `ConnectionResolverInterface → SingleConnectionResolver` and `EntityRepositoryFactory` as singletons with a default `SqlStorageDriver` driver factory. |
| `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php` | **Create** | Seven unit tests covering the factory contract against `InMemoryStorageDriver`. |
| `packages/entity-storage/composer.json` | **Modify** | Add `extra.waaseyaa.providers` entry so `PackageManifestCompiler` discovers `EntityStorageServiceProvider`. |
| `docs/specs/entity-system.md` | **Modify** | New section "Wiring per-entity-type repositories" documenting the factory contract and canonical consumer snippet. |

One file = one responsibility. Each file is small enough (<150 LOC) to reason about in one pass.

---

## Task 1: Create worktree and feature branch

**Files:** none (git operation only)

- [ ] **Step 1: Verify framework repo is clean**

Run:
```bash
cd /home/fsd42/dev/waaseyaa
git status -sb
```

Expected: `## main...origin/main` with no unstaged/uncommitted changes. If dirty, stop and resolve before proceeding.

- [ ] **Step 2: Create worktree**

Run:
```bash
cd /home/fsd42/dev/waaseyaa
git worktree add -b feature/1128-entity-repository-factory /home/fsd42/dev/waaseyaa-sync-entity-repository-factory main
cd /home/fsd42/dev/waaseyaa-sync-entity-repository-factory
```

Expected: new worktree at that path, branch checked out.

- [ ] **Step 3: Install deps in the worktree**

Run:
```bash
composer install --no-interaction --no-progress 2>&1 | tail -5
```

Expected: "Generating autoload files" and no errors.

- [ ] **Step 4: Verify baseline tests green**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage 2>&1 | tail -10
```

Expected: `OK (...)` — every existing test passes. If any fail on main, stop and report; this plan assumes green baseline.

---

## Task 2: Failing test — `get_returns_entity_repository_for_registered_type`

**Files:**
- Create: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\EntityStorage\Driver\EntityStorageDriverInterface;
use Waaseyaa\EntityStorage\Driver\InMemoryStorageDriver;
use Waaseyaa\EntityStorage\EntityRepositoryFactory;
use Waaseyaa\EntityStorage\Tests\Fixtures\TestStorageEntity;

#[CoversClass(EntityRepositoryFactory::class)]
final class EntityRepositoryFactoryTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->entityTypeManager = new EntityTypeManager($this->eventDispatcher);
        $this->entityTypeManager->registerEntityType($this->makeTestEntityType('test_entity'));
    }

    #[Test]
    public function getReturnsEntityRepositoryForRegisteredType(): void
    {
        $factory = $this->makeFactory();

        $repository = $factory->get('test_entity');

        $this->assertInstanceOf(EntityRepositoryInterface::class, $repository);
    }

    private function makeFactory(
        ?\Closure $driverFactory = null,
    ): EntityRepositoryFactory {
        return new EntityRepositoryFactory(
            entityTypeManager: $this->entityTypeManager,
            driverFactory: $driverFactory ?? fn(EntityTypeInterface $t): EntityStorageDriverInterface => new InMemoryStorageDriver(),
            eventDispatcher: $this->eventDispatcher,
        );
    }

    private function makeTestEntityType(string $id): EntityType
    {
        return new EntityType(
            id: $id,
            label: 'Test Entity',
            class: TestStorageEntity::class,
            keys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
        );
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php 2>&1 | tail -15
```

Expected: fatal error `Class "Waaseyaa\EntityStorage\EntityRepositoryFactory" not found` — confirms the test targets a class that doesn't exist yet.

---

## Task 3: Minimal `EntityRepositoryFactory` implementation

**Files:**
- Create: `packages/entity-storage/src/EntityRepositoryFactory.php`

- [ ] **Step 1: Create the class**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Event\EntityEventFactoryInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Entity\Validation\EntityValidator;
use Waaseyaa\EntityStorage\Driver\EntityStorageDriverInterface;
use Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver;

/**
 * Builds and memoizes EntityRepository instances by entity type id.
 *
 * Consumers use `$factory->get('community')` instead of hand-assembling
 * the 3-7 dependencies EntityRepository's constructor requires. The driver
 * is constructed per entity type via the injected driver factory closure,
 * matching the pattern of EntityTypeManager's storage factory.
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
}
```

- [ ] **Step 2: Run the test and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php 2>&1 | tail -10
```

Expected: `OK (1 test, 1 assertion)`.

- [ ] **Step 3: Commit**

```bash
git add packages/entity-storage/src/EntityRepositoryFactory.php packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php
git commit -m "feat(entity-storage): scaffold EntityRepositoryFactory with first test"
```

---

## Task 4: Test — `get_memoizes_same_instance`

**Files:**
- Modify: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

- [ ] **Step 1: Add the test method after `getReturnsEntityRepositoryForRegisteredType`**

```php
    #[Test]
    public function getMemoizesSameInstance(): void
    {
        $factory = $this->makeFactory();

        $first = $factory->get('test_entity');
        $second = $factory->get('test_entity');

        $this->assertSame($first, $second);
    }
```

- [ ] **Step 2: Run and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php --filter getMemoizesSameInstance 2>&1 | tail -5
```

Expected: `OK (1 test, 1 assertion)`. Memoization is already in the minimal implementation.

---

## Task 5: Test — `get_builds_distinct_instances_for_different_types`

**Files:**
- Modify: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

- [ ] **Step 1: Add the test**

```php
    #[Test]
    public function getBuildsDistinctInstancesForDifferentTypes(): void
    {
        $this->entityTypeManager->registerEntityType($this->makeTestEntityType('other_entity'));
        $factory = $this->makeFactory();

        $first = $factory->get('test_entity');
        $second = $factory->get('other_entity');

        $this->assertNotSame($first, $second);
    }
```

- [ ] **Step 2: Run and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php --filter getBuildsDistinctInstancesForDifferentTypes 2>&1 | tail -5
```

Expected: `OK (1 test, 1 assertion)`.

---

## Task 6: Test — `get_throws_for_unknown_entity_type`

**Files:**
- Modify: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

- [ ] **Step 1: Add the test**

```php
    #[Test]
    public function getThrowsForUnknownEntityType(): void
    {
        $factory = $this->makeFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity type "nonexistent" is not registered.');

        $factory->get('nonexistent');
    }
```

- [ ] **Step 2: Run and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php --filter getThrowsForUnknownEntityType 2>&1 | tail -5
```

Expected: `OK (1 test, 2 assertions)`. The exception bubbles up from `EntityTypeManager::getDefinition()` unchanged.

---

## Task 7: Test — `returned_repository_can_save_and_load_entity`

**Files:**
- Modify: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

- [ ] **Step 1: Add the test**

```php
    #[Test]
    public function returnedRepositoryCanSaveAndLoadEntity(): void
    {
        $factory = $this->makeFactory();
        $repository = $factory->get('test_entity');

        $entity = new TestStorageEntity(
            values: ['id' => '1', 'label' => 'Hello', 'bundle' => 'article', 'langcode' => 'en'],
            entityTypeId: 'test_entity',
            entityKeys: [
                'id' => 'id',
                'uuid' => 'uuid',
                'bundle' => 'bundle',
                'label' => 'label',
                'langcode' => 'langcode',
            ],
        );
        $entity->enforceIsNew(true);

        $repository->save($entity);
        $loaded = $repository->find('1');

        $this->assertNotNull($loaded);
        $this->assertSame('Hello', $loaded->get('label'));
    }
```

- [ ] **Step 2: Run and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php --filter returnedRepositoryCanSaveAndLoadEntity 2>&1 | tail -10
```

Expected: `OK (1 test, 2 assertions)`. This proves the closure, event dispatcher, and memoization all wire correctly end-to-end against `InMemoryStorageDriver`.

---

## Task 8: Test — `driver_factory_receives_entity_type`

**Files:**
- Modify: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`

- [ ] **Step 1: Add the test**

```php
    #[Test]
    public function driverFactoryReceivesEntityType(): void
    {
        $receivedType = null;
        $spy = function (EntityTypeInterface $entityType) use (&$receivedType): EntityStorageDriverInterface {
            $receivedType = $entityType;
            return new InMemoryStorageDriver();
        };

        $factory = $this->makeFactory(driverFactory: $spy);
        $factory->get('test_entity');

        $this->assertNotNull($receivedType);
        $this->assertSame('test_entity', $receivedType->id());
    }
```

- [ ] **Step 2: Run and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php --filter driverFactoryReceivesEntityType 2>&1 | tail -5
```

Expected: `OK (1 test, 2 assertions)`.

---

## Task 9: Test + implement `has()`

**Files:**
- Modify: `packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php`
- Modify: `packages/entity-storage/src/EntityRepositoryFactory.php`

- [ ] **Step 1: Add the failing test**

```php
    #[Test]
    public function hasDelegatesToEntityTypeManager(): void
    {
        $factory = $this->makeFactory();

        $this->assertTrue($factory->has('test_entity'));
        $this->assertFalse($factory->has('nonexistent'));
    }
```

- [ ] **Step 2: Run and verify it fails**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php --filter hasDelegatesToEntityTypeManager 2>&1 | tail -5
```

Expected: fatal error `Call to undefined method ... has()`.

- [ ] **Step 3: Add `has()` to the factory**

Add after the `get()` method in `packages/entity-storage/src/EntityRepositoryFactory.php`:

```php
    public function has(string $entityTypeId): bool
    {
        return $this->entityTypeManager->hasDefinition($entityTypeId);
    }
```

- [ ] **Step 4: Run and verify it passes**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php 2>&1 | tail -5
```

Expected: `OK (7 tests, 10 assertions)`.

- [ ] **Step 5: Commit**

```bash
git add packages/entity-storage/src/EntityRepositoryFactory.php packages/entity-storage/tests/Unit/EntityRepositoryFactoryTest.php
git commit -m "test(entity-storage): cover EntityRepositoryFactory contract"
```

---

## Task 10: Regression check — full entity-storage suite

**Files:** none (test run only)

- [ ] **Step 1: Run full package suite**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage 2>&1 | tail -15
```

Expected: every existing test still green plus the 7 new ones. If any regression, investigate before moving on — no existing file was modified so regressions would be unexpected.

- [ ] **Step 2: Run phpstan on the package**

Run:
```bash
./vendor/bin/phpstan analyse packages/entity-storage 2>&1 | tail -15
```

Expected: no new errors at the existing level. If phpstan flags the new file, fix inline and re-run.

---

## Task 11: Create `EntityStorageServiceProvider`

**Files:**
- Create: `packages/entity-storage/src/EntityStorageServiceProvider.php`

- [ ] **Step 1: Create the provider**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Connection\ConnectionResolverInterface;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\EntityStorageDriverInterface;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

/**
 * First service provider for the entity-storage package.
 *
 * Registers a default SingleConnectionResolver so SqlStorageDriver can be
 * constructed out of the box, plus the EntityRepositoryFactory itself.
 * Consumers that need a different connection topology (multi-tenant
 * scoping, multiple databases) should rebind ConnectionResolverInterface
 * and/or EntityRepositoryFactory in their own provider.
 */
final class EntityStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(
            ConnectionResolverInterface::class,
            fn(): ConnectionResolverInterface => new SingleConnectionResolver(
                $this->resolve(DatabaseInterface::class),
            ),
        );

        $this->singleton(
            EntityRepositoryFactory::class,
            fn(): EntityRepositoryFactory => new EntityRepositoryFactory(
                entityTypeManager: $this->resolve(EntityTypeManager::class),
                driverFactory: $this->defaultDriverFactory(),
                eventDispatcher: $this->resolve(EventDispatcherInterface::class),
            ),
        );
    }

    /**
     * @return Closure(EntityTypeInterface): EntityStorageDriverInterface
     */
    private function defaultDriverFactory(): Closure
    {
        return function (EntityTypeInterface $entityType): EntityStorageDriverInterface {
            $keys = $entityType->getKeys();
            $idKey = $keys['id'] ?? 'id';

            return new SqlStorageDriver(
                connectionResolver: $this->resolve(ConnectionResolverInterface::class),
                idKey: $idKey,
            );
        };
    }
}
```

**Why these specific kernel resolves work:**
- `DatabaseInterface::class` — provided by `ProviderRegistry::setKernelResolver` (verified in `packages/foundation/src/Kernel/Bootstrap/ProviderRegistry.php:60`).
- `EntityTypeManager::class` — provided by the same kernel resolver (line 58).
- `EventDispatcherInterface::class` — provided by the same kernel resolver (line 63).
- `ConnectionResolverInterface::class` — resolved from this provider's own bindings (resolution loops through all providers' bindings as a fallback, see the `foreach ($this->providers as $other)` block in the same file).

- [ ] **Step 2: Commit the provider file**

```bash
git add packages/entity-storage/src/EntityStorageServiceProvider.php
git commit -m "feat(entity-storage): add service provider with default bindings"
```

Note: No unit test for the provider itself. The framework convention (see `packages/auth/src/AuthServiceProvider.php`, `packages/media/src/MediaServiceProvider.php`) is to test the classes a provider registers, not the provider's `register()` method. The factory's behavior is fully covered by Task 2–9.

---

## Task 12: Wire provider discovery in composer.json

**Files:**
- Modify: `packages/entity-storage/composer.json`

- [ ] **Step 1: Read the current composer.json**

```bash
cat packages/entity-storage/composer.json
```

Confirm that the `extra` block contains only `branch-alias` and has no `waaseyaa.providers` key yet.

- [ ] **Step 2: Add providers entry to the `extra` block**

Replace the existing `extra` block:

```json
    "extra": {
        "branch-alias": {
            "dev-main": "0.1.x-dev",
            "dev-develop/v1.1": "0.1.x-dev"
        }
    }
```

with:

```json
    "extra": {
        "branch-alias": {
            "dev-main": "0.1.x-dev",
            "dev-develop/v1.1": "0.1.x-dev"
        },
        "waaseyaa": {
            "providers": ["Waaseyaa\\EntityStorage\\EntityStorageServiceProvider"]
        }
    }
```

- [ ] **Step 3: Verify JSON is valid**

Run:
```bash
php -r 'json_decode(file_get_contents("packages/entity-storage/composer.json"), flags: JSON_THROW_ON_ERROR); echo "ok\n";'
```

Expected: `ok`.

- [ ] **Step 4: Force manifest recompilation**

Run:
```bash
rm -f storage/framework/cache/package-manifest.php 2>/dev/null || true
composer dump-autoload 2>&1 | tail -3
```

Expected: "Generated optimized autoload files". If the manifest cache path is different in this project, the framework will regenerate it on next boot — that's fine.

- [ ] **Step 5: Commit**

```bash
git add packages/entity-storage/composer.json
git commit -m "chore(entity-storage): register EntityStorageServiceProvider via manifest"
```

---

## Task 13: Document the factory in `docs/specs/entity-system.md`

**Files:**
- Modify: `docs/specs/entity-system.md`

- [ ] **Step 1: Locate the insertion point**

Run:
```bash
grep -n '^## \|^### ' docs/specs/entity-system.md | head -40
```

Find an appropriate section heading such as "Storage" or "Repositories". If neither exists, the new section goes just before the first section after the overview. Note the line number.

- [ ] **Step 2: Insert the new section**

Use `Edit` to insert the following block at the chosen location. If a similar section already exists, extend it rather than duplicating — match the existing heading level (`##` or `###`) and voice.

```markdown
## Wiring per-entity-type repositories

`Waaseyaa\EntityStorage\EntityRepositoryFactory` builds and memoizes
`EntityRepositoryInterface` instances by entity type id. It delegates
definition lookup to `EntityTypeManager`, constructs a per-type
`EntityStorageDriverInterface` via an injected driver-factory closure,
and forwards all optional dependencies (validator, revision driver,
database, event factory) to the underlying `EntityRepository`.

`EntityStorageServiceProvider` registers the factory as a container
singleton with a default `SqlStorageDriver` driver factory backed by
`SingleConnectionResolver`. Consumers wire domain repositories like
this:

```php
$this->singleton(CommunityRepositoryInterface::class, fn() =>
    new CommunityRepository(
        $this->resolve(EntityRepositoryFactory::class)->get('community'),
    ),
);
```

Consumers that need a different connection topology (multi-tenant
scoping, per-community databases) should rebind either
`ConnectionResolverInterface` or `EntityRepositoryFactory` in their
own service provider; the latter accepts any closure that returns an
`EntityStorageDriverInterface` for a given `EntityTypeInterface`.
```

- [ ] **Step 3: Spec-drift detector**

Run whatever spec-drift / docs-lint command the framework repo uses (check `composer.json` scripts, `Taskfile.yml`, or top-level `Makefile`). Common candidates:

```bash
composer run-script spec-drift 2>/dev/null || \
./vendor/bin/waaseyaa spec:drift 2>/dev/null || \
./bin/drift-detector.sh 2>/dev/null || \
echo "no drift detector found — skip for now"
```

Expected: no drift reported. If the detector exists and complains about the new section, fix inline and re-run.

- [ ] **Step 4: Commit**

```bash
git add docs/specs/entity-system.md
git commit -m "docs(entity-system): document EntityRepositoryFactory wiring"
```

---

## Task 14: Final verification gates

**Files:** none (test + analysis run)

- [ ] **Step 1: Full `packages/entity-storage` suite**

Run:
```bash
./vendor/bin/phpunit packages/entity-storage 2>&1 | tail -10
```

Expected: all tests green, including the 7 new `EntityRepositoryFactoryTest` cases.

- [ ] **Step 2: Full framework test suite**

Run:
```bash
./vendor/bin/phpunit 2>&1 | tail -15
```

Expected: entire framework suite green. No other package should regress — the new provider is only loaded if discovered, but the new file additions shouldn't break anything.

- [ ] **Step 3: phpstan on the whole repo**

Run:
```bash
./vendor/bin/phpstan analyse 2>&1 | tail -20
```

Expected: no new errors at the existing level. Fix any flagged by the new code.

- [ ] **Step 4: Verify branch state**

Run:
```bash
git log --oneline main..HEAD
```

Expected: 5–6 focused commits matching the Task structure (scaffold, tests, provider, composer, docs).

---

## Task 15: Push and open PR

**Files:** none (git + gh)

- [ ] **Step 1: Push the branch**

Run:
```bash
git push -u origin feature/1128-entity-repository-factory
```

- [ ] **Step 2: Open the PR**

Run:
```bash
gh pr create -R waaseyaa/framework \
  --title "feat(entity-storage): EntityRepositoryFactory + service provider" \
  --body "$(cat <<'EOF'
## Summary

- Adds `Waaseyaa\EntityStorage\EntityRepositoryFactory` — a memoizing factory that builds `EntityRepositoryInterface` instances from an entity type id, delegating definition lookup to `EntityTypeManager` and driver construction to an injected closure.
- Adds `Waaseyaa\EntityStorage\EntityStorageServiceProvider` (first provider in this package) which registers `ConnectionResolverInterface → SingleConnectionResolver` and `EntityRepositoryFactory` as singletons with a default `SqlStorageDriver` driver factory.
- Documents the wiring pattern in `docs/specs/entity-system.md`.

No changes to `packages/entity`, `EntityTypeManager`, or any driver class.

Closes #1128. Unblocks downstream work on waaseyaa/giiken#42.

## Design notes

- The factory lives in `entity-storage`, not on `EntityTypeManager`, to preserve the `entity → entity-storage` package dependency direction (the reverse would be required if the method lived on `EntityTypeManager`).
- The driver factory is a closure, not a single driver instance, because `SqlStorageDriver` is per-entity-type (its `$idKey` derives from `EntityTypeInterface::getKeys()`). Closure pattern mirrors `EntityTypeManager::__construct(..., ?Closure $storageFactory = null)`.

## Test plan

- [x] 7 new unit cases in `EntityRepositoryFactoryTest` against `InMemoryStorageDriver` (returns a repo, memoization, distinct instances per id, throws for unknown id, end-to-end save/load, driver closure receives entity type, `has()` delegation)
- [x] Full `packages/entity-storage` suite green
- [x] Full framework test suite green
- [x] phpstan clean at existing level
- [ ] Reviewer to verify spec-drift passes in CI

Spec: `docs/superpowers/specs/2026-04-06-entity-repository-factory-design.md` in waaseyaa/giiken.
EOF
)"
```

Expected: `gh` prints the PR URL. Report it back.

- [ ] **Step 3: Return to main working directory and clean up**

Run:
```bash
cd /home/fsd42/dev/waaseyaa
git worktree list
```

Leave the worktree in place until the PR is merged — it's useful if reviewers request changes.

---

## Self-review

**Spec coverage check:**

| Spec requirement | Task(s) |
|---|---|
| New `EntityRepositoryFactory.php` | 3, 9 |
| New `EntityStorageServiceProvider.php` | 11 |
| New `EntityRepositoryFactoryTest.php` | 2, 4, 5, 6, 7, 8, 9 |
| `composer.json` provider discovery metadata | 12 |
| `docs/specs/entity-system.md` update | 13 |
| 7 test cases from the spec table | 2, 4, 5, 6, 7, 8, 9 |
| `phpunit` + `phpstan` gates | 10, 14 |
| Downstream probe from giiken | **deferred** — belongs to waaseyaa/giiken#42 PR, not this one (explicit in the spec's out-of-scope list) |

**Plan additions beyond the spec:**
- Task 11 also registers `ConnectionResolverInterface → SingleConnectionResolver`. Rationale: `SqlStorageDriver` can't be constructed without a resolver, and the spec's "default driver is `SqlStorageDriver`" requirement forces us to bind it. One-line addition, same package, no new concepts. Not scope creep.

**Placeholder scan:** no "TBD", "TODO", or "fill in later" markers. Every step shows exact code or exact commands. Task 13's docs insertion location is specified by a grep command whose output is inspected live, not guessed.

**Type consistency check:**
- Method name: `get()` everywhere (Tasks 2, 4, 5, 6, 7, 11). No drift to `getRepository()`.
- Constructor param name: `entityTypeManager` everywhere (Tasks 3, 11). `driverFactory` everywhere. No drift.
- Import paths verified from live grep in Task 0 preparation: `Waaseyaa\Entity\Repository\EntityRepositoryInterface`, `Waaseyaa\Entity\Validation\EntityValidator`, `Waaseyaa\EntityStorage\Connection\ConnectionResolverInterface`, `Waaseyaa\EntityStorage\Driver\RevisionableStorageDriver`, `Waaseyaa\EntityStorage\Driver\SqlStorageDriver`, `Waaseyaa\EntityStorage\Driver\InMemoryStorageDriver`.
- Kernel-resolvable symbols verified against `packages/foundation/src/Kernel/Bootstrap/ProviderRegistry.php:55-82`: `EntityTypeManager`, `DatabaseInterface`, `EventDispatcherInterface` — all provided.

No gaps found.
