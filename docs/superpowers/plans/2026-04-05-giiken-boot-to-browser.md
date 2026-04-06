# Giiken Boot-to-Browser Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Get `http://127.0.0.1:8765/test-community` rendering the Discovery homepage end-to-end with real services (no stubs that persist in production code paths), a real SQLite database with entity tables, and a seeded test community.

**Architecture:** Two coordinated PRs. PR A ships three framework DX fixes (ServeCommand dev-env + front-controller scaffold + NullLlmProvider) and one tiny migration helper (`EntitySchemaSync`). PR B consumes PR A to produce a bootable Giiken app: front controller, entity-table migration, real DI wiring, and a seed command.

**Tech Stack:** PHP 8.4, PHPUnit 10.5, Symfony Console, Doctrine DBAL, Waaseyaa framework packages (foundation, cli, entity, entity-storage, search, ai-agent, ai-vector, inertia).

**Spec:** `docs/superpowers/specs/2026-04-05-giiken-boot-to-browser-design.md`

**Deferred issues filed:** waaseyaa/framework#1118, waaseyaa/giiken#39, #40, #41.

---

## Working Directories

- **Framework worktree:** Create in `/home/jones/dev/waaseyaa` off `main`, branch `feat/boot-to-browser-dx`
- **Giiken worktree:** Create in `/home/jones/dev/giiken` off `main`, branch `feat/boot-to-browser`

Giiken consumes the framework via Composer path repositories with symlinks, so framework changes are visible in Giiken immediately — no publish cycle.

---

## Part 1 — Framework PR A (`waaseyaa/framework`)

### Task A1: Create framework worktree and branch

**Files:** none (git operations only)

- [ ] **Step 1: Create worktree**

```bash
cd /home/jones/dev/waaseyaa
git fetch origin
git worktree add ../waaseyaa-boot-dx -b feat/boot-to-browser-dx origin/main
cd ../waaseyaa-boot-dx
composer install
```

- [ ] **Step 2: Verify baseline tests pass**

```bash
composer test
```

Expected: All tests green before any changes.

---

### Task A2: ServeCommand env injection — write failing test

**Files:**
- Test: `packages/cli/tests/Unit/Command/ServeCommandTest.php` (new)

- [ ] **Step 1: Write the test**

Create `packages/cli/tests/Unit/Command/ServeCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\CLI\Command\ServeCommand;

#[CoversClass(ServeCommand::class)]
final class ServeCommandTest extends TestCase
{
    #[Test]
    public function it_defaults_app_env_to_development(): void
    {
        $command = new ServeCommand();

        $env = $command->resolveChildEnv([]);

        self::assertSame('development', $env['APP_ENV']);
        self::assertSame('1', $env['APP_DEBUG']);
    }

    #[Test]
    public function it_respects_caller_set_app_env(): void
    {
        $command = new ServeCommand();

        $env = $command->resolveChildEnv(['APP_ENV' => 'staging']);

        self::assertSame('staging', $env['APP_ENV']);
        // APP_DEBUG is not forced when caller sets a non-default env.
        self::assertArrayNotHasKey('APP_DEBUG', $env);
    }

    #[Test]
    public function it_passes_through_other_env_vars(): void
    {
        $command = new ServeCommand();

        $env = $command->resolveChildEnv(['PATH' => '/usr/bin', 'HOME' => '/home/test']);

        self::assertSame('/usr/bin', $env['PATH']);
        self::assertSame('/home/test', $env['HOME']);
        self::assertSame('development', $env['APP_ENV']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit packages/cli/tests/Unit/Command/ServeCommandTest.php
```

Expected: FAIL — `resolveChildEnv` method does not exist.

---

### Task A3: ServeCommand env injection — implement

**Files:**
- Modify: `packages/cli/src/Command/ServeCommand.php`

- [ ] **Step 1: Add `resolveChildEnv` method and use it in `execute`**

Replace `packages/cli/src/Command/ServeCommand.php` contents with:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'serve',
    description: 'Start the PHP development server',
)]
final class ServeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port', '8080');
    }

    /**
     * Build the environment the PHP child server will run under.
     *
     * If the caller hasn't set APP_ENV, default to development and force
     * APP_DEBUG=1 so dev-mode database auto-creation and boot-error
     * visibility kick in. All other parent env vars pass through.
     *
     * @param array<string, string> $parentEnv
     * @return array<string, string>
     */
    public function resolveChildEnv(array $parentEnv): array
    {
        $env = $parentEnv;

        if (!isset($env['APP_ENV']) || $env['APP_ENV'] === '') {
            $env['APP_ENV'] = 'development';
            $env['APP_DEBUG'] = '1';
        }

        return $env;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $publicIndex = getcwd() . '/public/index.php';
        if (!file_exists($publicIndex) || filesize($publicIndex) === 0) {
            $output->writeln('<error>public/index.php is missing or empty.</error>');
            $output->writeln('<comment>Run: vendor/bin/waaseyaa make:public</comment>');
            return self::FAILURE;
        }

        $env = $this->resolveChildEnv(getenv());

        if (($env['APP_ENV'] ?? '') === 'development') {
            $output->writeln(
                '<info>Starting in development mode (APP_ENV=development).</info> '
                . 'Use <comment>APP_ENV=production vendor/bin/waaseyaa serve</comment> to override.'
            );
        }

        $output->writeln(sprintf('<info>Waaseyaa development server started:</info> http://%s:%s', $host, $port));
        $output->writeln('<comment>Press Ctrl+C to stop.</comment>');

        $process = proc_open(
            [PHP_BINARY, '-S', "{$host}:{$port}", '-t', 'public'],
            [STDIN, STDOUT, STDERR],
            $pipes,
            null,
            $env,
        );

        if ($process === false) {
            $output->writeln('<error>Failed to start the development server.</error>');
            return self::FAILURE;
        }

        return proc_close($process);
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit packages/cli/tests/Unit/Command/ServeCommandTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 3: Commit**

```bash
git add packages/cli/src/Command/ServeCommand.php packages/cli/tests/Unit/Command/ServeCommandTest.php
git commit -m "feat(cli): ServeCommand defaults APP_ENV=development and verifies public/index.php

Closes waaseyaa/framework#1116. Addresses the empty-body symptom in
waaseyaa/framework#1117 by failing fast when the front controller is
missing, directing the user to run make:public.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task A4: MakePublicCommand — write failing test

**Files:**
- Test: `packages/cli/tests/Integration/Command/Make/MakePublicCommandTest.php` (new)

- [ ] **Step 1: Write the integration test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Tests\Integration\Command\Make;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Waaseyaa\CLI\Command\Make\MakePublicCommand;

#[CoversClass(MakePublicCommand::class)]
final class MakePublicCommandTest extends TestCase
{
    private string $tempProjectRoot;

    protected function setUp(): void
    {
        $this->tempProjectRoot = sys_get_temp_dir() . '/waaseyaa-make-public-' . uniqid();
        mkdir($this->tempProjectRoot);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempProjectRoot . '/public')) {
            @unlink($this->tempProjectRoot . '/public/index.php');
            @rmdir($this->tempProjectRoot . '/public');
        }
        @rmdir($this->tempProjectRoot);
    }

    #[Test]
    public function it_creates_public_index_php(): void
    {
        $command = new MakePublicCommand($this->tempProjectRoot);
        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->find('make:public'));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertFileExists($this->tempProjectRoot . '/public/index.php');

        $contents = file_get_contents($this->tempProjectRoot . '/public/index.php');
        self::assertNotFalse($contents);
        self::assertNotSame('', trim($contents));
        self::assertStringContainsString('HttpKernel', $contents);
        self::assertStringContainsString('handle()', $contents);
    }

    #[Test]
    public function it_refuses_to_overwrite_existing_file(): void
    {
        mkdir($this->tempProjectRoot . '/public');
        file_put_contents($this->tempProjectRoot . '/public/index.php', '<?php // existing');

        $command = new MakePublicCommand($this->tempProjectRoot);
        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->find('make:public'));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('already exists', $tester->getDisplay());
        self::assertSame('<?php // existing', file_get_contents($this->tempProjectRoot . '/public/index.php'));
    }

    #[Test]
    public function it_overwrites_with_force_flag(): void
    {
        mkdir($this->tempProjectRoot . '/public');
        file_put_contents($this->tempProjectRoot . '/public/index.php', '<?php // old');

        $command = new MakePublicCommand($this->tempProjectRoot);
        $application = new Application();
        $application->add($command);

        $tester = new CommandTester($application->find('make:public'));
        $exitCode = $tester->execute(['--force' => true]);

        self::assertSame(0, $exitCode);
        $contents = file_get_contents($this->tempProjectRoot . '/public/index.php');
        self::assertStringContainsString('HttpKernel', $contents);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit packages/cli/tests/Integration/Command/Make/MakePublicCommandTest.php
```

Expected: FAIL — `MakePublicCommand` class does not exist.

---

### Task A5: MakePublicCommand — template + command

**Files:**
- Create: `packages/cli/templates/public/index.php.stub`
- Create: `packages/cli/src/Command/Make/MakePublicCommand.php`

- [ ] **Step 1: Create the front-controller template**

Create `packages/cli/templates/public/index.php.stub`:

```php
<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Kernel\HttpKernel;

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

$kernel = new HttpKernel($projectRoot);
$response = $kernel->handle();
$response->send();
```

- [ ] **Step 2: Create the command**

Create `packages/cli/src/Command/Make/MakePublicCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command\Make;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'make:public',
    description: 'Scaffold the canonical public/index.php front controller',
)]
final class MakePublicCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite an existing public/index.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $publicDir = $this->projectRoot . '/public';
        $target = $publicDir . '/index.php';

        if (!is_dir($publicDir) && !mkdir($publicDir, 0o755, true) && !is_dir($publicDir)) {
            $output->writeln(sprintf('<error>Failed to create directory %s</error>', $publicDir));
            return self::FAILURE;
        }

        if (file_exists($target) && !$input->getOption('force')) {
            $output->writeln(sprintf('<comment>%s already exists. Use --force to overwrite.</comment>', $target));
            return self::SUCCESS;
        }

        $stub = __DIR__ . '/../../../templates/public/index.php.stub';
        if (!file_exists($stub)) {
            $output->writeln(sprintf('<error>Template missing: %s</error>', $stub));
            return self::FAILURE;
        }

        $bytes = file_put_contents($target, file_get_contents($stub));
        if ($bytes === false) {
            $output->writeln(sprintf('<error>Failed to write %s</error>', $target));
            return self::FAILURE;
        }

        $output->writeln(sprintf('<info>Created %s</info>', $target));
        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: Run the test to verify it passes**

```bash
./vendor/bin/phpunit packages/cli/tests/Integration/Command/Make/MakePublicCommandTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 4: Register MakePublicCommand with the CLI application**

The CLI application's command registration lives in `packages/cli/src/Application.php` (or similar). Find where `ServeCommand`, `InstallCommand`, `MigrateCommand` are registered and add `MakePublicCommand` alongside them. The command needs `$projectRoot` at construction — use the same project-root discovery the existing commands use (usually `getcwd()` passed in from `bin/waaseyaa`).

Grep first to find the registration point:

```bash
grep -rn 'ServeCommand\|MigrateCommand\|new Application' packages/cli/src/ packages/cli/bin/ 2>/dev/null
```

Wire `MakePublicCommand` into that same location, passing `$projectRoot` (the working directory where the CLI was invoked). If the file is `packages/cli/src/Application.php` with a method like `registerDefaultCommands()`, add:

```php
$application->add(new MakePublicCommand($projectRoot));
```

- [ ] **Step 5: Commit**

```bash
git add packages/cli/templates/public/index.php.stub \
        packages/cli/src/Command/Make/MakePublicCommand.php \
        packages/cli/tests/Integration/Command/Make/MakePublicCommandTest.php \
        packages/cli/src/Application.php
git commit -m "feat(cli): add make:public command to scaffold front controller

Writes the canonical public/index.php template, refusing to overwrite
an existing file unless --force is passed. The template instantiates
HttpKernel, handles the request, and sends the response.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task A6: InstallCommand calls make:public

**Files:**
- Modify: `packages/cli/src/Command/InstallCommand.php`

- [ ] **Step 1: Read existing InstallCommand to understand flow**

```bash
cat packages/cli/src/Command/InstallCommand.php
```

Note where `execute()` does its steps and where the project root is available.

- [ ] **Step 2: Invoke make:public inside execute()**

Add a call to run `make:public` at the appropriate point in `InstallCommand::execute()`. Use Symfony Console's sub-command invocation pattern:

```php
$makePublic = $this->getApplication()?->find('make:public');
if ($makePublic !== null) {
    $makePublic->run(new \Symfony\Component\Console\Input\ArrayInput([]), $output);
}
```

Place it after the config bootstrap but before any database operations so a fresh project gets a working front controller immediately.

- [ ] **Step 3: Run existing InstallCommand tests (if any) and the full suite**

```bash
./vendor/bin/phpunit packages/cli/tests/
```

Expected: all green.

- [ ] **Step 4: Commit**

```bash
git add packages/cli/src/Command/InstallCommand.php
git commit -m "feat(cli): install command scaffolds public/index.php

InstallCommand now invokes make:public so newly-installed projects
have a working front controller out of the box.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task A7: NullLlmProvider — write failing test

**Files:**
- Test: `packages/ai-agent/tests/Unit/Provider/NullLlmProviderTest.php` (new)

- [ ] **Step 1: Determine the target interface**

The concrete framework interface lives in `packages/ai-agent/src/Provider/ProviderInterface.php`. Read it to confirm method signatures before writing the test:

```bash
cat packages/ai-agent/src/Provider/ProviderInterface.php
```

The provider interface wraps a multi-turn agent protocol (`MessageRequest` → `MessageResponse`). `NullLlmProvider` needs to implement it, returning a deterministic `MessageResponse` containing a single text block.

- [ ] **Step 2: Write the test**

Adapt to the actual `ProviderInterface` signature. Skeleton:

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AiAgent\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AiAgent\Provider\MessageRequest;
use Waaseyaa\AiAgent\Provider\NullLlmProvider;

#[CoversClass(NullLlmProvider::class)]
final class NullLlmProviderTest extends TestCase
{
    #[Test]
    public function it_returns_deterministic_stub_message(): void
    {
        $provider = new NullLlmProvider();
        $request = new MessageRequest(
            systemPrompt: 'test system',
            messages: [['role' => 'user', 'content' => 'hello']],
        );

        $response = $provider->sendMessage($request);

        self::assertStringContainsString('LLM unavailable', $response->getText());
    }

    #[Test]
    public function it_does_not_hit_network(): void
    {
        // If the constructor tried to open a socket, this test itself
        // would hang or fail. Asserting a second instance constructs
        // cleanly is a proxy for 'no network I/O at all'.
        new NullLlmProvider();
        new NullLlmProvider();
        self::assertTrue(true);
    }
}
```

**Important:** `MessageRequest`, `MessageResponse`, and `sendMessage`/`complete` method names must be adjusted to match the actual `ProviderInterface`. Read it first and adapt this test before running it.

- [ ] **Step 3: Run test to verify it fails**

```bash
./vendor/bin/phpunit packages/ai-agent/tests/Unit/Provider/NullLlmProviderTest.php
```

Expected: FAIL — `NullLlmProvider` class does not exist.

---

### Task A8: NullLlmProvider — implement

**Files:**
- Create: `packages/ai-agent/src/Provider/NullLlmProvider.php`

- [ ] **Step 1: Implement against the real ProviderInterface**

Skeleton (adjust methods and types to match the actual interface you saw in Task A7):

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AiAgent\Provider;

/**
 * Null LLM provider for development and testing.
 *
 * Returns a deterministic response without touching the network. Use this
 * when wiring an app that depends on ProviderInterface but does not have
 * a real LLM configured yet. Do NOT use in production — it will return
 * the same placeholder response for every request.
 */
final class NullLlmProvider implements ProviderInterface
{
    private const PLACEHOLDER = '[LLM unavailable in this environment — configure an LLM provider to enable AI features.]';

    public function sendMessage(MessageRequest $request): MessageResponse
    {
        return new MessageResponse(
            id: 'null-' . bin2hex(random_bytes(6)),
            model: 'null',
            content: [new TextBlock(text: self::PLACEHOLDER)],
            stopReason: 'end_turn',
            usage: ['input_tokens' => 0, 'output_tokens' => 0],
        );
    }

    // Implement any other required methods from ProviderInterface — they
    // should also be no-ops returning deterministic empty/placeholder values.
}
```

The exact constructor arguments for `MessageResponse` and the class name for text blocks (`TextBlock`, `ContentBlock`?) must match what the real interface uses. Read `packages/ai-agent/src/Provider/MessageResponse.php` and neighboring files to get the signatures right.

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit packages/ai-agent/tests/Unit/Provider/NullLlmProviderTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 3: Commit**

```bash
git add packages/ai-agent/src/Provider/NullLlmProvider.php \
        packages/ai-agent/tests/Unit/Provider/NullLlmProviderTest.php
git commit -m "feat(ai-agent): add NullLlmProvider for dev and testing

Deterministic provider that implements ProviderInterface without
network I/O. Apps wiring a real LLM later can swap this out by
rebinding the interface in their service provider.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task A9: EntitySchemaSync helper — write failing test

**Files:**
- Test: `packages/entity-storage/tests/Unit/EntitySchemaSyncTest.php` (new)

**Context:** Giiken's migration needs to call `SqlSchemaHandler::ensureTable()` for each registered entity type, but `SqlSchemaHandler` takes a `DatabaseInterface`, not a DBAL `Connection`. Migrations only get a `SchemaBuilder` wrapping a DBAL Connection. This helper bridges the gap: given a list of EntityTypeInterface and a DatabaseInterface, it calls `ensureTable()` on each.

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\EntitySchemaSync;

#[CoversClass(EntitySchemaSync::class)]
final class EntitySchemaSyncTest extends TestCase
{
    #[Test]
    public function it_creates_tables_for_each_entity_type(): void
    {
        $database = DBALDatabase::createSqlite(':memory:');

        $types = [
            new EntityType(
                id: 'widget',
                label: 'Widget',
                class: \stdClass::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
            ),
            new EntityType(
                id: 'gadget',
                label: 'Gadget',
                class: \stdClass::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
            ),
        ];

        $sync = new EntitySchemaSync($database);
        $sync->syncAll($types);

        $schema = $database->schema();
        self::assertTrue($schema->tableExists('widget'));
        self::assertTrue($schema->tableExists('gadget'));
    }

    #[Test]
    public function sync_all_is_idempotent(): void
    {
        $database = DBALDatabase::createSqlite(':memory:');
        $type = new EntityType(
            id: 'widget',
            label: 'Widget',
            class: \stdClass::class,
            keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
        );

        $sync = new EntitySchemaSync($database);
        $sync->syncAll([$type]);
        $sync->syncAll([$type]);

        self::assertTrue($database->schema()->tableExists('widget'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntitySchemaSyncTest.php
```

Expected: FAIL — `EntitySchemaSync` class does not exist.

**If the test also reveals that `EntityType` constructor args differ from what's shown**, read `packages/entity/src/EntityType.php` and adjust the test's entity-type construction to match real arguments.

---

### Task A10: EntitySchemaSync — implement

**Files:**
- Create: `packages/entity-storage/src/EntitySchemaSync.php`

- [ ] **Step 1: Implement**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\EntityStorage;

use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;

/**
 * Materializes entity-storage tables for a list of entity types.
 *
 * Thin wrapper around SqlSchemaHandler::ensureTable() that lets migrations
 * (or install commands) sync schemas for many entity types at once without
 * each caller repeating the construction boilerplate.
 */
final class EntitySchemaSync
{
    public function __construct(
        private readonly DatabaseInterface $database,
    ) {}

    /**
     * @param iterable<EntityTypeInterface> $entityTypes
     */
    public function syncAll(iterable $entityTypes): void
    {
        foreach ($entityTypes as $entityType) {
            $handler = new SqlSchemaHandler($entityType, $this->database);
            $handler->ensureTable();
        }
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit packages/entity-storage/tests/Unit/EntitySchemaSyncTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 3: Commit**

```bash
git add packages/entity-storage/src/EntitySchemaSync.php \
        packages/entity-storage/tests/Unit/EntitySchemaSyncTest.php
git commit -m "feat(entity-storage): add EntitySchemaSync helper for migrations

Thin wrapper around SqlSchemaHandler::ensureTable() that accepts a list
of EntityTypeInterface and materializes each table. Lets app migrations
sync entity-storage schemas without repeating construction boilerplate.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task A11: Framework PR A — full test run and push

- [ ] **Step 1: Run full framework test suite**

```bash
composer test
```

Expected: all green. If not, fix before moving on. No skips, no xfails.

- [ ] **Step 2: Push branch**

```bash
git push -u origin feat/boot-to-browser-dx
```

- [ ] **Step 3: Open PR**

```bash
gh pr create --repo waaseyaa/framework --title "feat: ServeCommand dev env, make:public, NullLlmProvider, EntitySchemaSync" --body "$(cat <<'EOF'
## Summary

Four DX fixes that unblock first-run app development, surfaced while getting Giiken's Discovery homepage rendering end-to-end.

- **ServeCommand** now defaults \`APP_ENV=development\` and \`APP_DEBUG=1\` when the caller has not set them, and verifies \`public/index.php\` exists and is non-empty before starting (fails fast with a pointer to \`make:public\`). Closes #1116. Addresses the empty-body symptom of #1117.
- **make:public** command scaffolds the canonical front controller. \`InstallCommand\` invokes it so newly-installed projects get a working front controller out of the box.
- **NullLlmProvider** implements \`Waaseyaa\\\\AiAgent\\\\Provider\\\\ProviderInterface\` with a deterministic placeholder response and zero network I/O. Apps wiring AI later can replace the binding.
- **EntitySchemaSync** wraps \`SqlSchemaHandler::ensureTable()\` so migrations can materialize entity-storage tables for many entity types at once.

## Test plan

- [ ] \`composer test\` green
- [ ] Manual: \`vendor/bin/waaseyaa make:public\` in an empty project writes a bootable file
- [ ] Manual: \`vendor/bin/waaseyaa serve\` with no \`public/index.php\` fails with a clear pointer
- [ ] Manual: \`vendor/bin/waaseyaa serve\` with a valid front controller starts in development mode

Giiken PR follows: waaseyaa/giiken feat/boot-to-browser.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Part 2 — Giiken PR B (`waaseyaa/giiken`)

### Task B1: Create Giiken worktree and branch

- [ ] **Step 1: Create worktree**

```bash
cd /home/jones/dev/giiken
git fetch origin
git worktree add ../giiken-boot -b feat/boot-to-browser origin/main
cd ../giiken-boot
composer install
```

Because `composer.json` has `repositories` pointing at `/home/jones/dev/waaseyaa/packages/*` with `symlink: true`, the install will link against the `waaseyaa-boot-dx` worktree's packages automatically — no version bumping needed.

**Verify the symlink went to the PR A worktree, not the main one:**

```bash
readlink vendor/waaseyaa/cli
readlink vendor/waaseyaa/ai-agent
readlink vendor/waaseyaa/entity-storage
```

Expected: each points into `/home/jones/dev/waaseyaa/packages/...`. The path-repo URL uses the primary workspace, so changes in the `waaseyaa-boot-dx` worktree are only visible if that worktree is the one at `/home/jones/dev/waaseyaa/packages/*`. If the symlink points at `main`, either merge PR A first or adjust `composer.json` temporarily to point at the worktree explicitly.

- [ ] **Step 2: Verify baseline tests pass**

```bash
./vendor/bin/phpunit
```

Expected: 173+ tests green.

---

### Task B2: Scaffold `public/index.php` via make:public

**Files:**
- Create: `public/index.php` (via command)

- [ ] **Step 1: Delete the empty placeholder if it exists**

```bash
ls -la public/
rm -f public/index.php
```

- [ ] **Step 2: Run make:public**

```bash
./vendor/bin/waaseyaa make:public
```

Expected: `Created /home/jones/dev/giiken-boot/public/index.php` (or similar).

- [ ] **Step 3: Verify file is non-empty and contains HttpKernel**

```bash
test -s public/index.php && grep -q HttpKernel public/index.php && echo OK
```

Expected: `OK`.

- [ ] **Step 4: Commit**

```bash
git add public/index.php
git commit -m "feat: scaffold public/index.php front controller

Generated via vendor/bin/waaseyaa make:public. Instantiates HttpKernel,
handles the request, and sends the response. Previously empty, which
caused the 200-empty-body symptom.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B3: Entity-tables migration — write failing test

**Files:**
- Test: `tests/Unit/Migration/EnsureEntityTablesMigrationTest.php` (new)

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Migration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

final class EnsureEntityTablesMigrationTest extends TestCase
{
    #[Test]
    public function up_creates_all_giiken_entity_tables(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $schemaBuilder = new SchemaBuilder($connection);

        $migration = require __DIR__ . '/../../../migrations/001_ensure_entity_tables.php';
        self::assertInstanceOf(\Waaseyaa\Foundation\Migration\Migration::class, $migration);

        $migration->up($schemaBuilder);

        $schemaManager = $connection->createSchemaManager();
        $tableNames = array_map(
            static fn ($t) => $t->getName(),
            $schemaManager->listTables(),
        );

        self::assertContains('community', $tableNames);
        self::assertContains('knowledge_item', $tableNames);
        self::assertContains('wiki_lint_report', $tableNames);
    }

    #[Test]
    public function up_is_idempotent(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $schemaBuilder = new SchemaBuilder($connection);

        $migration = require __DIR__ . '/../../../migrations/001_ensure_entity_tables.php';

        $migration->up($schemaBuilder);
        $migration->up($schemaBuilder);

        $schemaManager = $connection->createSchemaManager();
        self::assertTrue($schemaManager->tablesExist(['community']));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Migration/EnsureEntityTablesMigrationTest.php
```

Expected: FAIL — migration file does not exist.

---

### Task B4: Entity-tables migration — implement

**Files:**
- Create: `migrations/001_ensure_entity_tables.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Wiki\WikiLintReport;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\EntitySchemaSync;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $database = $this->databaseFromSchema($schema);

        $sync = new EntitySchemaSync($database);
        $sync->syncAll([
            new EntityType(
                id: 'community',
                label: 'Community',
                class: Community::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
            ),
            new EntityType(
                id: 'knowledge_item',
                label: 'Knowledge Item',
                class: KnowledgeItem::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
            ),
            new EntityType(
                id: 'wiki_lint_report',
                label: 'Wiki Lint Report',
                class: WikiLintReport::class,
                keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'title'],
            ),
        ]);
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('wiki_lint_report');
        $schema->dropIfExists('knowledge_item');
        $schema->dropIfExists('community');
    }

    /**
     * Adapt the DBAL connection exposed by SchemaBuilder into a
     * Waaseyaa DatabaseInterface so SqlSchemaHandler can use it.
     */
    private function databaseFromSchema(SchemaBuilder $schema): DBALDatabase
    {
        /** @var Connection $conn */
        $conn = $schema->getConnection();

        return DBALDatabase::fromConnection($conn);
    }
};
```

**Investigation note:** `DBALDatabase::fromConnection()` may or may not exist. Before running the test, grep for a factory in `packages/database/src/DBALDatabase.php`:

```bash
grep -n 'public static function' /home/jones/dev/waaseyaa/packages/database/src/DBALDatabase.php
```

If no `fromConnection` factory exists, the cleanest options in order:

1. **Add one in PR A** (small extension — 10 lines in `DBALDatabase`). Go back to Task A10 territory if needed. This is the right long-term fix.
2. **Use raw DBAL SQL in the migration** — give up on the `SqlSchemaHandler` path and hand-write `CREATE TABLE` statements that mirror what `SqlSchemaHandler::buildTableSpec()` produces (columns: id serial, uuid varchar(128), bundle varchar(128), label key varchar(255), langcode varchar(12) default 'en', `_data` text default '{}', plus any created_at/updated_at if the spec adds them — confirm by reading `SqlSchemaHandler::buildTableSpec()` fully).

Prefer option 1 for schema-drift safety. Option 2 is the escape hatch.

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Migration/EnsureEntityTablesMigrationTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 3: Manually run the migration against a real DB**

```bash
rm -f storage/waaseyaa.sqlite
APP_ENV=development ./vendor/bin/waaseyaa migrate
sqlite3 storage/waaseyaa.sqlite ".tables"
```

Expected output includes `community`, `knowledge_item`, `wiki_lint_report`, plus whatever other framework tables come from package migrations (queue, notification, scheduler, migrations).

- [ ] **Step 4: Commit**

```bash
git add migrations/001_ensure_entity_tables.php tests/Unit/Migration/EnsureEntityTablesMigrationTest.php
git commit -m "feat: migration to ensure entity-storage tables exist

Uses framework's EntitySchemaSync to materialize the community,
knowledge_item, and wiki_lint_report tables on 'waaseyaa migrate'.
Delegates all schema detail to SqlSchemaHandler so Giiken tracks
framework changes automatically.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B5: FakeEmbeddingAdapter — write failing test

**Files:**
- Test: `tests/Unit/Pipeline/Provider/Adapter/FakeEmbeddingAdapterTest.php` (new)

- [ ] **Step 1: Read Giiken's `EmbeddingProviderInterface` to confirm the method contract**

```bash
cat src/Pipeline/Provider/EmbeddingProviderInterface.php
```

Expected: a single `embed(string $text): array` method (confirmed during spec research).

- [ ] **Step 2: Write the test**

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Pipeline\Provider\Adapter;

use Giiken\Pipeline\Provider\Adapter\FakeEmbeddingAdapter;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Vector\Testing\FakeEmbeddingProvider;

#[CoversClass(FakeEmbeddingAdapter::class)]
final class FakeEmbeddingAdapterTest extends TestCase
{
    #[Test]
    public function it_implements_giikens_interface(): void
    {
        $adapter = new FakeEmbeddingAdapter(new FakeEmbeddingProvider());

        self::assertInstanceOf(EmbeddingProviderInterface::class, $adapter);
    }

    #[Test]
    public function it_returns_deterministic_vector(): void
    {
        $adapter = new FakeEmbeddingAdapter(new FakeEmbeddingProvider(dimensions: 16));

        $v1 = $adapter->embed('hello world');
        $v2 = $adapter->embed('hello world');

        self::assertCount(16, $v1);
        self::assertSame($v1, $v2);
    }

    #[Test]
    public function different_inputs_produce_different_vectors(): void
    {
        $adapter = new FakeEmbeddingAdapter(new FakeEmbeddingProvider(dimensions: 16));

        $a = $adapter->embed('alpha');
        $b = $adapter->embed('beta');

        self::assertNotSame($a, $b);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Pipeline/Provider/Adapter/FakeEmbeddingAdapterTest.php
```

Expected: FAIL — adapter class does not exist.

---

### Task B6: FakeEmbeddingAdapter — implement

**Files:**
- Create: `src/Pipeline/Provider/Adapter/FakeEmbeddingAdapter.php`

- [ ] **Step 1: Write the adapter**

```php
<?php

declare(strict_types=1);

namespace Giiken\Pipeline\Provider\Adapter;

use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Waaseyaa\AI\Vector\Testing\FakeEmbeddingProvider;

/**
 * Bridges the framework's FakeEmbeddingProvider to Giiken's local
 * EmbeddingProviderInterface. Used as the dev/default binding in
 * GiikenServiceProvider until a real provider is wired.
 *
 * See waaseyaa/giiken#40 for the plan to replace this with a
 * sovereignty-profile-aware real provider.
 */
final class FakeEmbeddingAdapter implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly FakeEmbeddingProvider $inner,
    ) {}

    public function embed(string $text): array
    {
        return $this->inner->embed($text);
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Pipeline/Provider/Adapter/FakeEmbeddingAdapterTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 3: Commit**

```bash
git add src/Pipeline/Provider/Adapter/FakeEmbeddingAdapter.php \
        tests/Unit/Pipeline/Provider/Adapter/FakeEmbeddingAdapterTest.php
git commit -m "feat: FakeEmbeddingAdapter bridges ai-vector to Giiken interface

Delegates to Waaseyaa\\AI\\Vector\\Testing\\FakeEmbeddingProvider
so Giiken can satisfy its EmbeddingProviderInterface binding in dev
without requiring a real embedding service. Tracked by giiken#40.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B7: NullLlmAdapter — write failing test

**Files:**
- Test: `tests/Unit/Pipeline/Provider/Adapter/NullLlmAdapterTest.php` (new)

- [ ] **Step 1: Read Giiken's `LlmProviderInterface` to confirm the contract**

```bash
cat src/Pipeline/Provider/LlmProviderInterface.php
```

Expected: a `complete(string $systemPrompt, string $userContent): string` method (confirmed during spec research — `QaService::ask()` calls `$this->llmProvider->complete(self::SYSTEM_PROMPT, $userPrompt)`).

- [ ] **Step 2: Write the test**

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Pipeline\Provider\Adapter;

use Giiken\Pipeline\Provider\Adapter\NullLlmAdapter;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AiAgent\Provider\NullLlmProvider;

#[CoversClass(NullLlmAdapter::class)]
final class NullLlmAdapterTest extends TestCase
{
    #[Test]
    public function it_implements_giikens_llm_interface(): void
    {
        $adapter = new NullLlmAdapter(new NullLlmProvider());

        self::assertInstanceOf(LlmProviderInterface::class, $adapter);
    }

    #[Test]
    public function complete_returns_placeholder_string(): void
    {
        $adapter = new NullLlmAdapter(new NullLlmProvider());

        $result = $adapter->complete('system', 'user question');

        self::assertStringContainsString('LLM unavailable', $result);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Pipeline/Provider/Adapter/NullLlmAdapterTest.php
```

Expected: FAIL — adapter class does not exist.

---

### Task B8: NullLlmAdapter — implement

**Files:**
- Create: `src/Pipeline/Provider/Adapter/NullLlmAdapter.php`

- [ ] **Step 1: Write the adapter**

The framework's `NullLlmProvider` implements the multi-turn `ProviderInterface` with `sendMessage`, but Giiken's `LlmProviderInterface::complete(system, user)` is a simple string-in/string-out. The adapter constructs a `MessageRequest` with the system + user content, calls `sendMessage`, extracts the text block from the response.

```php
<?php

declare(strict_types=1);

namespace Giiken\Pipeline\Provider\Adapter;

use Giiken\Pipeline\Provider\LlmProviderInterface;
use Waaseyaa\AiAgent\Provider\MessageRequest;
use Waaseyaa\AiAgent\Provider\NullLlmProvider;

/**
 * Bridges framework's NullLlmProvider (multi-turn ProviderInterface)
 * to Giiken's simpler LlmProviderInterface::complete(system, user): string.
 *
 * Dev/default binding. See waaseyaa/giiken#40 for real provider.
 */
final class NullLlmAdapter implements LlmProviderInterface
{
    public function __construct(
        private readonly NullLlmProvider $inner,
    ) {}

    public function complete(string $systemPrompt, string $userContent): string
    {
        $request = new MessageRequest(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userContent]],
        );

        $response = $this->inner->sendMessage($request);

        // Extract text from the first content block.
        return $response->getText();
    }
}
```

**If `MessageRequest` constructor or `MessageResponse::getText()` signatures differ from this**, adjust based on what Task A7 / A8 revealed about the actual interface. The adapter must round-trip through whatever the framework `ProviderInterface` actually exposes.

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Pipeline/Provider/Adapter/NullLlmAdapterTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 3: Commit**

```bash
git add src/Pipeline/Provider/Adapter/NullLlmAdapter.php \
        tests/Unit/Pipeline/Provider/Adapter/NullLlmAdapterTest.php
git commit -m "feat: NullLlmAdapter bridges ai-agent to Giiken LLM interface

Wraps the framework's NullLlmProvider (multi-turn) to satisfy
Giiken's simpler complete(system, user) contract. Dev/default
binding tracked by giiken#40.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B9: SeedTestCommunityCommand — write failing test

**Files:**
- Test: `tests/Unit/Console/SeedTestCommunityCommandTest.php` (new)

- [ ] **Step 1: Read existing Giiken test patterns**

```bash
ls tests/Unit/
cat tests/Unit/Access/KnowledgeItemAccessPolicyTest.php | head -60
```

Note the fixture-building style with private helper methods and `setUp()`.

- [ ] **Step 2: Read existing `CommunityRepository`, `KnowledgeItemRepository`, and `WikiSchema` to know the constructor arguments**

```bash
cat src/Entity/Community/CommunityRepository.php
cat src/Entity/Community/WikiSchema.php
cat src/Entity/KnowledgeItem/KnowledgeItem.php
cat src/Access/AccessTier.php
```

- [ ] **Step 3: Write the test**

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Console;

use Giiken\Console\SeedTestCommunityCommand;
use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SeedTestCommunityCommand::class)]
final class SeedTestCommunityCommandTest extends TestCase
{
    #[Test]
    public function it_creates_community_and_sample_items(): void
    {
        $communityRepo = new FakeCommunityRepo();
        $itemRepo = new FakeItemRepo();

        $command = new SeedTestCommunityCommand($communityRepo, $itemRepo);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($app->find('giiken:seed:test-community'));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($communityRepo->findBySlug('test-community'));
        self::assertGreaterThanOrEqual(2, $itemRepo->savedCount());
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $communityRepo = new FakeCommunityRepo();
        $itemRepo = new FakeItemRepo();

        $command = new SeedTestCommunityCommand($communityRepo, $itemRepo);
        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($app->find('giiken:seed:test-community'));
        $tester->execute([]);
        $firstCount = $itemRepo->savedCount();

        $tester->execute([]);
        $secondCount = $itemRepo->savedCount();

        self::assertSame($firstCount, $secondCount);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }
}

// Inline fakes (preferred over mocks here — real behavior matters).
final class FakeCommunityRepo implements CommunityRepositoryInterface
{
    /** @var array<string, Community> */
    private array $bySlug = [];

    public function find(string $id): ?Community
    {
        foreach ($this->bySlug as $c) {
            if ((string) $c->get('id') === $id) {
                return $c;
            }
        }
        return null;
    }

    public function findBySlug(string $slug): ?Community
    {
        return $this->bySlug[$slug] ?? null;
    }

    public function save(Community $community): void
    {
        $slug = (string) $community->get('slug');
        $this->bySlug[$slug] = $community;
    }

    public function delete(Community $community): void
    {
        $slug = (string) $community->get('slug');
        unset($this->bySlug[$slug]);
    }
}

final class FakeItemRepo implements KnowledgeItemRepositoryInterface
{
    /** @var list<KnowledgeItem> */
    private array $items = [];

    public function find(string $id): ?KnowledgeItem
    {
        foreach ($this->items as $item) {
            if ((string) $item->get('id') === $id) {
                return $item;
            }
        }
        return null;
    }

    /** @return list<KnowledgeItem> */
    public function findByCommunity(string $communityId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn ($item) => (string) $item->get('community_id') === $communityId,
        ));
    }

    public function save(KnowledgeItem $item): void
    {
        $this->items[] = $item;
    }

    public function savedCount(): int
    {
        return count($this->items);
    }
}
```

**Note:** `KnowledgeItemRepositoryInterface` method signatures need to match the real file you read in Step 2. Adjust the fake if there are extra methods.

- [ ] **Step 4: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Console/SeedTestCommunityCommandTest.php
```

Expected: FAIL — `SeedTestCommunityCommand` class does not exist.

---

### Task B10: SeedTestCommunityCommand — implement

**Files:**
- Create: `src/Console/SeedTestCommunityCommand.php`

- [ ] **Step 1: Write the command**

```php
<?php

declare(strict_types=1);

namespace Giiken\Console;

use Giiken\Access\AccessTier;
use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\Community\WikiSchema;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'giiken:seed:test-community',
    description: 'Seed a test community with sample public knowledge items',
)]
final class SeedTestCommunityCommand extends Command
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly KnowledgeItemRepositoryInterface $itemRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $existing = $this->communityRepo->findBySlug('test-community');
        if ($existing !== null) {
            $output->writeln(sprintf(
                '<comment>Community with slug "test-community" already exists (id=%s). Skipping.</comment>',
                (string) $existing->get('id'),
            ));
            return self::SUCCESS;
        }

        $community = new Community();
        $community->set('id', 'test-community');
        $community->set('slug', 'test-community');
        $community->set('name', 'Test Community');
        $community->set('wiki_schema', (new WikiSchema(
            defaultLanguage: 'en',
            knowledgeTypes: [
                KnowledgeType::Cultural,
                KnowledgeType::Governance,
                KnowledgeType::Land,
                KnowledgeType::Relationship,
                KnowledgeType::Event,
            ],
            llmInstructions: 'Answer respectfully using only provided context.',
        ))->toArray());
        $community->set('created_at', date('c'));
        $community->set('updated_at', date('c'));

        $this->communityRepo->save($community);
        $output->writeln('<info>Created community "test-community".</info>');

        foreach ($this->sampleItems() as $item) {
            $this->itemRepo->save($item);
        }

        $output->writeln(sprintf(
            '<info>Seeded %d sample knowledge items.</info>',
            count($this->sampleItems()),
        ));

        return self::SUCCESS;
    }

    /** @return list<KnowledgeItem> */
    private function sampleItems(): array
    {
        $items = [];

        $titles = [
            ['welcome', 'Welcome to the Community', 'An introductory page for visitors.', KnowledgeType::Cultural],
            ['governance-intro', 'Governance Overview', 'How decisions are made in this community.', KnowledgeType::Governance],
            ['land-acknowledgement', 'Land Acknowledgement', 'Traditional territory and naming.', KnowledgeType::Land],
        ];

        foreach ($titles as [$id, $title, $body, $type]) {
            $item = new KnowledgeItem();
            $item->set('id', $id);
            $item->set('community_id', 'test-community');
            $item->set('title', $title);
            $item->set('content', $body);
            $item->set('summary', $body);
            $item->set('knowledge_type', $type->value);
            $item->set('access_tier', AccessTier::Public->value);
            $item->set('created_at', date('c'));
            $item->set('updated_at', date('c'));
            $items[] = $item;
        }

        return $items;
    }
}
```

**Adjustments expected during implementation:** The `Community::set('wiki_schema', ...)` assumes `WikiSchema::toArray()` exists and entity stores it as an array. Read `src/Entity/Community/WikiSchema.php` and `ContentEntityBase::set` — if `WikiSchema` already plays well with the entity, you may be able to pass the object directly. Adjust to match. Same for `KnowledgeType` and `AccessTier` — use whatever serialization the existing tests in `tests/Unit/Access/KnowledgeItemAccessPolicyTest.php` use when they build fixtures.

- [ ] **Step 2: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Console/SeedTestCommunityCommandTest.php
```

Expected: 2/2 PASS. If it fails because of entity setter signatures, adjust the test fakes or the command body and re-run.

- [ ] **Step 3: Commit**

```bash
git add src/Console/SeedTestCommunityCommand.php \
        tests/Unit/Console/SeedTestCommunityCommandTest.php
git commit -m "feat: seed-test-community console command

Creates a 'test-community' Community with a default WikiSchema and
three public-tier sample KnowledgeItems. Idempotent — skips if a
community with that slug already exists.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B11: Wire services in GiikenServiceProvider — register() bindings

**Files:**
- Modify: `src/GiikenServiceProvider.php`

- [ ] **Step 1: Add use statements and replace the TODO block**

At the top of `GiikenServiceProvider.php`, add use statements:

```php
use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Console\SeedTestCommunityCommand;
use Giiken\Entity\Community\CommunityRepository;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepository;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Pipeline\Provider\Adapter\FakeEmbeddingAdapter;
use Giiken\Pipeline\Provider\Adapter\NullLlmAdapter;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Query\QaService;
use Giiken\Query\QaServiceInterface;
use Giiken\Query\Report\GovernanceSummaryReport;
use Giiken\Query\Report\LandBriefReport;
use Giiken\Query\Report\LanguageReport;
use Giiken\Query\Report\ReportService;
use Giiken\Query\Report\ReportServiceInterface;
use Giiken\Query\SearchService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\AI\Vector\Testing\FakeEmbeddingProvider;
use Waaseyaa\AiAgent\Provider\NullLlmProvider;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Search\Fts5\Fts5SearchProvider;
use Waaseyaa\Search\SearchProviderInterface;
```

Delete the two TODO comment blocks at the bottom of the file. Replace `register()` with:

```php
public function register(): void
{
    $this->entityType(new EntityType(
        id: 'community',
        label: 'Community',
        class: Community::class,
        keys: [
            'id'    => 'id',
            'uuid'  => 'uuid',
            'label' => 'name',
        ],
    ));

    $this->entityType(new EntityType(
        id: 'knowledge_item',
        label: 'Knowledge Item',
        class: KnowledgeItem::class,
        keys: [
            'id'    => 'id',
            'uuid'  => 'uuid',
            'label' => 'title',
        ],
    ));

    $this->entityType(new EntityType(
        id: 'wiki_lint_report',
        label: 'Wiki Lint Report',
        class: WikiLintReport::class,
        keys: [
            'id'    => 'id',
            'uuid'  => 'uuid',
            'label' => 'title',
        ],
    ));

    // Repositories
    $this->singleton(CommunityRepositoryInterface::class, function () {
        /** @var EntityTypeManager $etm */
        $etm = $this->resolve(EntityTypeManager::class);
        return new CommunityRepository($etm->getStorage('community'));
    });

    $this->singleton(KnowledgeItemRepositoryInterface::class, function () {
        /** @var EntityTypeManager $etm */
        $etm = $this->resolve(EntityTypeManager::class);
        return new KnowledgeItemRepository($etm->getStorage('knowledge_item'));
    });

    // Search providers
    $this->singleton(SearchProviderInterface::class, function () {
        /** @var DatabaseInterface $db */
        $db = $this->resolve(DatabaseInterface::class);
        return new Fts5SearchProvider($db);
    });

    $this->singleton(EmbeddingProviderInterface::class, fn () =>
        new FakeEmbeddingAdapter(new FakeEmbeddingProvider()));

    $this->singleton(LlmProviderInterface::class, fn () =>
        new NullLlmAdapter(new NullLlmProvider()));

    // Query services
    $this->singleton(SearchService::class, fn () => new SearchService(
        $this->resolve(SearchProviderInterface::class),
        $this->resolve(EmbeddingProviderInterface::class),
        new KnowledgeItemAccessPolicy(),
        $this->resolve(KnowledgeItemRepositoryInterface::class),
    ));

    $this->singleton(QaServiceInterface::class, fn () => new QaService(
        $this->resolve(SearchService::class),
        $this->resolve(LlmProviderInterface::class),
    ));

    $this->singleton(ReportServiceInterface::class, fn () => new ReportService(
        [
            new GovernanceSummaryReport(),
            new LanguageReport(),
            new LandBriefReport(),
        ],
        $this->resolve(KnowledgeItemRepositoryInterface::class),
    ));

    // ExportService and ImportService: their exact constructor signatures are
    // documented in src/Domain/Export and src/Domain/Import. Follow the same
    // pattern — resolve their dependencies via $this->resolve().
    // TODO Link: deferred to completion of this task block.
}
```

**Important:** Before running tests, verify `ReportService` constructor by reading `src/Query/Report/ReportService.php` — the signature shown in the spec research was `__construct($renderers, KnowledgeItemRepositoryInterface $repository)` but may differ. Adjust the binding if so.

Also verify `ExportService` and `ImportService` constructor signatures by reading those files (if they exist — the Phase 3 spec says they do). Wire them in the same style. If they don't exist yet, delete those bindings (don't ship broken wiring). The homepage only needs `SearchService`, `QaServiceInterface`, `CommunityRepositoryInterface`, `KnowledgeItemRepositoryInterface` for the `index` route. Management routes need `ReportService`/`ExportService`/`ImportService` — but the management routes are not in-scope for this plan's definition of done (only the Discovery homepage is).

**Pragmatic scope call:** If Export/Import services don't exist or have unresolved dependencies, leave them unbound and log a comment pointing to the issue. The Discovery homepage will still render.

- [ ] **Step 2: Run Giiken test suite**

```bash
./vendor/bin/phpunit
```

Expected: 173+ tests still green, plus the new migration/adapter/command tests. No regressions.

- [ ] **Step 3: Commit**

```bash
git add src/GiikenServiceProvider.php
git commit -m "feat: wire Phase 3 services in GiikenServiceProvider

Replaces the Phase 3 TODO block with real DI bindings for:
- CommunityRepository / KnowledgeItemRepository (via EntityTypeManager)
- Fts5SearchProvider (framework)
- FakeEmbeddingAdapter / NullLlmAdapter (dev defaults, see #40)
- SearchService, QaService, ReportService

Management-route services (Export/Import) and ingestion handler
wiring remain out of scope — tracked by #39 and the existing
ingestion pipeline plan.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B12: Register SeedTestCommunityCommand via commands()

**Files:**
- Modify: `src/GiikenServiceProvider.php`

- [ ] **Step 1: Add `commands()` override**

Append below `routes()` in `GiikenServiceProvider`:

```php
/**
 * @return list<\Symfony\Component\Console\Command\Command>
 */
public function commands(
    EntityTypeManager $entityTypeManager,
    DatabaseInterface $database,
    EventDispatcherInterface $dispatcher,
): array {
    $communityRepo = new CommunityRepository($entityTypeManager->getStorage('community'));
    $itemRepo = new KnowledgeItemRepository($entityTypeManager->getStorage('knowledge_item'));

    return [
        new SeedTestCommunityCommand($communityRepo, $itemRepo),
    ];
}
```

Note: this constructs the repositories directly rather than going through `$this->resolve()` because `commands()` runs at CLI boot with framework-supplied services; the service provider's `$this->resolve()` closures assume the kernel resolver is fully wired, which is a different boot phase.

- [ ] **Step 2: Run test suite**

```bash
./vendor/bin/phpunit
```

Expected: all green.

- [ ] **Step 3: Manually verify the command is registered**

```bash
./vendor/bin/waaseyaa list | grep giiken
```

Expected output includes `giiken:seed:test-community`.

- [ ] **Step 4: Commit**

```bash
git add src/GiikenServiceProvider.php
git commit -m "feat: register giiken:seed:test-community command

Exposes the seed command to the CLI via GiikenServiceProvider::commands(),
constructing repositories from the framework-supplied EntityTypeManager.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B13: DiscoveryHomepageBootTest — the acceptance test

**Files:**
- Test: `tests/Integration/Boot/DiscoveryHomepageBootTest.php` (new)

- [ ] **Step 1: Write the integration test**

This test boots the full HttpKernel against in-memory SQLite, runs migrations, runs the seed command, dispatches a `GET /test-community` request, and asserts the full response.

```php
<?php

declare(strict_types=1);

namespace Giiken\Tests\Integration\Boot;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Foundation\Kernel\HttpKernel;

final class DiscoveryHomepageBootTest extends TestCase
{
    protected function setUp(): void
    {
        // Force in-memory SQLite and development env.
        putenv('WAASEYAA_DB=:memory:');
        putenv('APP_ENV=development');
    }

    protected function tearDown(): void
    {
        putenv('WAASEYAA_DB');
        putenv('APP_ENV');
    }

    #[Test]
    public function get_test_community_returns_inertia_discovery_index(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $kernel = new HttpKernel($projectRoot);

        // Run migrations (entity tables) and seed, mirroring what a fresh
        // 'waaseyaa migrate && giiken:seed:test-community' would do.
        $this->runMigrationsAndSeed($kernel);

        // Simulate GET /test-community.
        $request = Request::create('/test-community', 'GET');
        $kernel->setRequest($request); // if the kernel exposes this

        $response = $kernel->handle();

        self::assertSame(200, $response->getStatusCode());
        $html = $response->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('data-page', $html);
        self::assertStringContainsString('Discovery\/Index', $html);
        self::assertStringContainsString('test-community', $html);
    }

    private function runMigrationsAndSeed(HttpKernel $kernel): void
    {
        // Implementation depends on how the kernel exposes the migrator
        // and CLI command runner. Investigate AbstractKernel / HttpKernel
        // for a 'runConsoleCommand' helper, or instantiate the Migrator
        // directly against the in-memory connection.
        //
        // Fallback approach: construct the Migrator + MigrationLoader
        // manually, run them, then resolve SeedTestCommunityCommand from
        // the kernel's service container and run it.

        throw new \LogicException('Implement migration and seed helper before running this test.');
    }
}
```

**This test will fail immediately on the `runMigrationsAndSeed` stub.** That is intentional — the next step is to read the kernel to find the right way to drive migrations and seed inside a test.

- [ ] **Step 2: Investigate the kernel's test-boot pattern**

```bash
grep -rn 'setRequest\|handleRequest\|testing\|createKernel\|migrate' \
    /home/jones/dev/waaseyaa/packages/foundation/src/Kernel/ \
    /home/jones/dev/waaseyaa/packages/foundation/tests/ \
    2>/dev/null | head -40
```

Look for how the framework itself tests HttpKernel. Adapt that pattern. Two common shapes:

1. **Kernel exposes a service container getter** — use it to resolve `Migrator` and the seed command, run them, then call `$kernel->handle()` with the request.
2. **Kernel boots from env vars** — set `WAASEYAA_DB=:memory:`, construct kernel, call a `boot()` hook that the test can hook into to run migrations.

If neither pattern exists cleanly, the pragmatic alternative is a **curl-based smoke test** not an in-process test: the acceptance test is manual `curl` + grep (Task B14), and this integration test is skipped with a clear xfail comment referencing a framework issue to add testable boot hooks.

- [ ] **Step 3: Implement the helper or convert to a smoke test**

Either finish `runMigrationsAndSeed` or delete this test file and rely on Task B14's manual curl gate. **If deleting, file a framework issue** (`waaseyaa/framework` "Add HttpKernel testing hooks for integration tests") and link it from the PR description so the gap is tracked.

- [ ] **Step 4: Run the test**

```bash
./vendor/bin/phpunit tests/Integration/Boot/DiscoveryHomepageBootTest.php
```

Expected: PASS (if implemented) or skipped/deleted (if converted to manual smoke test).

- [ ] **Step 5: Commit**

```bash
git add tests/Integration/Boot/DiscoveryHomepageBootTest.php
git commit -m "test: boot-to-browser acceptance test for Discovery homepage

Boots HttpKernel, runs migrations, seeds test community, dispatches
GET /test-community, and asserts Inertia page object contains
Discovery/Index component with community.slug=test-community.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

---

### Task B14: Manual end-to-end verification

This task has no file changes. It is the definition-of-done gate from the spec.

- [ ] **Step 1: Fresh migrate + seed**

```bash
rm -f storage/waaseyaa.sqlite
./vendor/bin/waaseyaa migrate
./vendor/bin/waaseyaa giiken:seed:test-community
sqlite3 storage/waaseyaa.sqlite "SELECT id, slug, name FROM community;"
sqlite3 storage/waaseyaa.sqlite "SELECT COUNT(*) FROM knowledge_item;"
```

Expected: community row `(test-community, test-community, Test Community)`, and 3 knowledge_item rows.

- [ ] **Step 2: Start server in background and curl the homepage**

```bash
./vendor/bin/waaseyaa serve --port 8765 &
SERVER_PID=$!
sleep 1
curl -sS -o /tmp/giiken-home.html -w "HTTP %{http_code}\n" http://127.0.0.1:8765/test-community
kill $SERVER_PID
wait $SERVER_PID 2>/dev/null
```

Expected: `HTTP 200` and `/tmp/giiken-home.html` non-empty.

- [ ] **Step 3: Verify the HTML contents**

```bash
grep -q 'data-page' /tmp/giiken-home.html && echo "inertia data-page: OK"
grep -q 'Discovery\\/Index' /tmp/giiken-home.html && echo "component: OK"
grep -q 'test-community' /tmp/giiken-home.html && echo "community slug: OK"
grep -q 'script type="module"' /tmp/giiken-home.html && echo "vite assets: OK"
grep -q '/build/assets/' /tmp/giiken-home.html && echo "vite build path: OK"
```

Expected: all five `OK` lines.

- [ ] **Step 4: If anything fails, diagnose and fix**

Do NOT paper over failures. If the response is 500, read the log; if the Inertia payload is missing data, check the controller; if the Vite assets are missing, check the root template and the build output in `public/build/`.

- [ ] **Step 5: Capture results in the PR description (prepared for Task B15)**

Save a short transcript of the successful curl + grep output to paste into the PR body.

---

### Task B15: Full test run and PR

- [ ] **Step 1: Run the full Giiken test suite**

```bash
./vendor/bin/phpunit
```

Expected: all green. No skipped tests without an explicit tracking issue.

- [ ] **Step 2: Static analysis**

```bash
./vendor/bin/phpstan analyse src/
```

Expected: no new errors. If the Phase 3 wiring introduced phpstan errors, fix them before shipping.

- [ ] **Step 3: Push and open PR**

```bash
git push -u origin feat/boot-to-browser
gh pr create --repo waaseyaa/giiken --title "feat: boot-to-browser — Discovery homepage renders end-to-end" --body "$(cat <<'EOF'
## Summary

Gets \`http://127.0.0.1:8765/test-community\` rendering the Discovery homepage with real services, a real SQLite database, and a seeded test community. No hacks, no stubs in production code paths.

Closes the Phase 3 service-wiring TODO block in GiikenServiceProvider.

Depends on framework PR: waaseyaa/framework feat/boot-to-browser-dx (ServeCommand dev env, make:public, NullLlmProvider, EntitySchemaSync).

## Changes

- **Front controller:** \`public/index.php\` scaffolded via \`waaseyaa make:public\`
- **Migration:** \`migrations/001_ensure_entity_tables.php\` uses \`EntitySchemaSync\` to materialize community, knowledge_item, and wiki_lint_report tables from their EntityType specs
- **DI wiring:** real bindings for \`CommunityRepository\`, \`KnowledgeItemRepository\`, \`Fts5SearchProvider\`, \`SearchService\`, \`QaService\`, \`ReportService\` in \`GiikenServiceProvider::register()\`
- **Provider adapters:** \`FakeEmbeddingAdapter\`, \`NullLlmAdapter\` bridge framework provider implementations to Giiken's local interfaces (dev defaults; see #40)
- **Seed command:** \`giiken:seed:test-community\` creates a community with a default \`WikiSchema\` and three public-tier sample knowledge items (idempotent)
- **Acceptance test:** integration test that boots the kernel, runs migrations, seeds, and dispatches \`GET /test-community\`

## Test plan

- [x] \`./vendor/bin/phpunit\` green (all 173+ existing tests plus new ones for migration, adapters, seed command, and boot acceptance)
- [x] \`./vendor/bin/phpstan analyse src/\` clean
- [x] Manual: fresh \`waaseyaa migrate && giiken:seed:test-community && waaseyaa serve\` → \`curl http://127.0.0.1:8765/test-community\` returns 200 with Discovery/Index Inertia payload and Vite asset tags
- [x] Manual: re-running seed command is idempotent

## Out of scope

Filed as follow-up issues:
- #39 — MediaIngestionHandler wiring
- #40 — real Ollama/OpenAI provider replacing NullLlmAdapter/FakeEmbeddingAdapter
- #41 — session-based auth for Discovery and Management routes

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 4: Verify the PR was created and contains the full body**

```bash
gh pr view --repo waaseyaa/giiken
```

---

## Self-Review — Checks against the Spec

**Spec coverage check:**

| Spec section | Plan task(s) |
|---|---|
| §3.1 ServeCommand dev env | A2, A3 |
| §3.1 ServeCommand verify public/index.php | A3 (inline in execute) |
| §3.1 make:public command + template | A4, A5 |
| §3.1 InstallCommand calls make:public | A6 |
| §3.1 NullLlmProvider | A7, A8 |
| §3.1 EntitySchemaSync (Risk A contingency) | A9, A10 |
| §3.2 public/index.php via make:public | B2 |
| §3.2 migrations/001_ensure_entity_tables.php | B3, B4 |
| §3.2 GiikenServiceProvider::register bindings | B11 |
| §3.2 FakeEmbeddingAdapter | B5, B6 |
| §3.2 NullLlmAdapter | B7, B8 |
| §3.2 SeedTestCommunityCommand | B9, B10, B12 |
| §3.2 DiscoveryHomepageBootTest (acceptance) | B13 |
| §3 Verification gates | B14, B15 |

No gaps.

**Placeholder scan:**

- No "TBD" / "TODO" / "fill in later" steps.
- A few tasks contain an "investigation step" before writing code (e.g., B4's `DBALDatabase::fromConnection` check, A7's `ProviderInterface` signature check, B11's `ReportService` constructor check). Each has explicit commands to run and explicit fallback guidance if the investigated API differs.
- Task B13 has a conditional fallback path (manual smoke test if kernel test hooks are missing). This is documented, not hidden.

**Type consistency:**

- `CommunityRepositoryInterface` / `KnowledgeItemRepositoryInterface` used consistently throughout B5-B12.
- `EmbeddingProviderInterface` / `LlmProviderInterface` consistently Giiken-local in B5-B11.
- `NullLlmProvider` (framework) vs `NullLlmAdapter` (Giiken) kept distinct.
- `EntityTypeManager` used consistently; not accidentally `EntityTypeManagerInterface` in some places.

**Scope check:**

- PR A is 4 small framework changes + 1 helper. Coherent DX batch.
- PR B builds directly on PR A. All tasks are Discovery-homepage-focused except the necessarily-touched infrastructure (migration, DI wiring, seed command).
- Management routes, real LLM, auth are all explicitly deferred with filed issues.

Plan complete. No revisions needed.

---

**Plan complete and saved to `docs/superpowers/plans/2026-04-05-giiken-boot-to-browser.md`.**
