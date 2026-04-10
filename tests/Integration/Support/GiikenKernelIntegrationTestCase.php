<?php

declare(strict_types=1);

namespace Giiken\Tests\Integration\Support;

use Giiken\GiikenServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\EntityStorage\EntityRepository as WaaseyaaEntityRepository;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Boots the real app kernel against :memory SQLite, runs pending migrations, and exposes helpers.
 */
abstract class GiikenKernelIntegrationTestCase extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;
    private static bool $booted = false;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');
        putenv('APP_ENV=local');
        putenv('APP_DEBUG=0');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        $result = self::$kernel->getMigrator()->run(self::$kernel->getMigrationLoader()->loadAll());
        self::assertGreaterThanOrEqual(0, $result->count, 'Migrations should run without throwing.');

        self::$booted = true;
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');
        putenv('APP_ENV');
        putenv('APP_DEBUG');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        self::$booted = false;
    }

    protected static function kernel(): HttpKernel
    {
        self::assertTrue(self::$booted, 'Kernel not booted');

        return self::$kernel;
    }

    protected static function giikenProvider(): GiikenServiceProvider
    {
        foreach (self::kernel()->getProviders() as $provider) {
            if ($provider instanceof GiikenServiceProvider) {
                return $provider;
            }
        }

        self::fail('GiikenServiceProvider not registered');
    }

    protected static function entityTypeManager(): EntityTypeManager
    {
        return self::kernel()->getEntityTypeManager();
    }

    protected static function entityRepositoryFor(string $entityTypeId): WaaseyaaEntityRepository
    {
        $etm = self::entityTypeManager();
        $database = self::kernel()->getDatabase();
        $dispatcher = self::giikenProvider()->resolve(PsrEventDispatcherInterface::class);
        self::assertInstanceOf(PsrEventDispatcherInterface::class, $dispatcher);

        $driver = new SqlStorageDriver(new SingleConnectionResolver($database), 'id');

        return new WaaseyaaEntityRepository(
            $etm->getDefinition($entityTypeId),
            $driver,
            $dispatcher,
            revisionDriver: null,
            database: $database,
        );
    }

    /**
     * {@see EntityRepository::save()} does not assign auto-increment ids back onto the entity; load by stable key.
     */
    protected static function assertFirstByUuid(WaaseyaaEntityRepository $repo, string $uuid): EntityInterface
    {
        $rows = $repo->findBy(['uuid' => $uuid], limit: 1);
        self::assertNotEmpty($rows, 'Expected a row with uuid ' . $uuid);

        return $rows[0];
    }

    protected static function entityDefinition(string $entityTypeId): EntityTypeInterface
    {
        return self::entityTypeManager()->getDefinition($entityTypeId);
    }

    protected static function database(): DatabaseInterface
    {
        return self::kernel()->getDatabase();
    }

    /**
     * Mirrors DiscoveryController / Inertia props: ISO string from the API must be safe for a {@code <time datetime="">} attribute.
     *
     * @return array{datetime: string, html: string}
     */
    protected static function formatTimeElementFromApiIso(string $iso8601FromApi): array
    {
        $dt = \Carbon\CarbonImmutable::parse($iso8601FromApi);
        $attr = $dt->toIso8601String();
        $html = sprintf(
            '<time datetime="%s">%s</time>',
            htmlspecialchars($attr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($dt->format('M j, Y'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );

        return ['datetime' => $attr, 'html' => $html];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    protected static function assertStorageBagsEqualIgnoringGeneratedKeys(array $a, array $b): void
    {
        $strip = static function (array $row): array {
            foreach (['id', 'revision_id', '_revision_id'] as $k) {
                unset($row[$k]);
            }

            return $row;
        };

        self::assertSame($strip($a), $strip($b));
    }
}
