<?php

declare(strict_types=1);

namespace Giiken\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class DiscoverPageTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        self::$kernel->getMigrator()->run(self::$kernel->getMigrationLoader()->loadAll());
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');
        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    #[Test]
    public function get_root_returns_inertia_discover_page(): void
    {
        $response = $this->handleKernelRequest('GET', '/', []);

        self::assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('"component":"Discover"', $content);
        // Giiken rewrites data-page="true" → data-page="app" so Inertia v2's
        // client reader (`script[data-page="app"]`) actually finds the page object.
        self::assertMatchesRegularExpression(
            '/<script[^>]+data-page="app"[^>]*>/',
            $content,
        );
    }

    #[Test]
    public function get_root_x_inertia_returns_json_page_object(): void
    {
        $response = $this->handleKernelRequest('GET', '/', [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'giiken',
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->headers->get('X-Inertia'));
        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('Discover', $decoded['component'] ?? null);
    }

    /**
     * @param array<string, string> $extraServer
     */
    private function handleKernelRequest(string $method, string $uri, array $extraServer): Response
    {
        $saved = $_SERVER;
        $_SERVER = array_merge($saved, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
        ], $extraServer);

        try {
            return self::$kernel->handle();
        } finally {
            $_SERVER = $saved;
        }
    }
}
