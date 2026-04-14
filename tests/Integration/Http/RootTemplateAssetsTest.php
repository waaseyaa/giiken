<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Regression guard for waaseyaa/giiken#90: the Inertia root template must
 * emit a `<script src>` for the Vite-built bundle. Without it, #app is
 * empty in every browser and Vue never mounts — even though the server
 * still returns 200 with a valid Inertia data-page JSON payload.
 *
 * A minimal Vite manifest is staged into public/build/.vite/ for the
 * duration of the test (preserving any real dev build) so this runs in
 * CI without requiring `npm run build` first.
 */
#[CoversNothing]
final class RootTemplateAssetsTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;
    private static string $manifestPath;
    private static ?string $manifestBackup = null;
    private static bool $manifestDirCreated = false;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);
        self::$manifestPath = self::$projectRoot . '/public/build/.vite/manifest.json';

        self::stageManifest();

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new ReflectionMethod(AbstractKernel::class, 'boot');
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

        self::restoreManifest();
    }

    #[Test]
    public function root_template_includes_vite_script_tag(): void
    {
        $response = $this->handleKernelRequest('GET', '/');
        self::assertSame(200, $response->getStatusCode());
        $content = (string) $response->getContent();

        self::assertMatchesRegularExpression(
            '#<script[^>]+src="[^"]*/build/assets/app-[^"]+\.js"#',
            $content,
            'Inertia root template must emit a <script src="/build/assets/app-*.js"> tag. '
            . 'If absent, ViteAssetManager could not find the manifest at '
            . 'public/build/.vite/manifest.json — likely a project-root path bug.',
        );
    }

    #[Test]
    public function root_template_includes_vite_stylesheet_link(): void
    {
        $response = $this->handleKernelRequest('GET', '/');
        $content = (string) $response->getContent();

        self::assertMatchesRegularExpression(
            '#<link[^>]+href="[^"]*/build/assets/app-[^"]+\.css"#',
            $content,
            'Inertia root template must emit a <link rel="stylesheet" href="/build/assets/app-*.css"> tag.',
        );
    }

    private static function stageManifest(): void
    {
        if (is_file(self::$manifestPath)) {
            self::$manifestBackup = (string) file_get_contents(self::$manifestPath);
            return;
        }

        $manifestDir = dirname(self::$manifestPath);
        if (!is_dir($manifestDir)) {
            mkdir($manifestDir, 0777, true);
            self::$manifestDirCreated = true;
        }

        file_put_contents(self::$manifestPath, (string) json_encode([
            'resources/js/app.ts' => [
                'file' => 'assets/app-test.js',
                'name' => 'app',
                'src' => 'resources/js/app.ts',
                'isEntry' => true,
                'css' => ['assets/app-test.css'],
            ],
        ], JSON_PRETTY_PRINT));
    }

    private static function restoreManifest(): void
    {
        if (self::$manifestBackup !== null) {
            file_put_contents(self::$manifestPath, self::$manifestBackup);
            return;
        }

        if (is_file(self::$manifestPath)) {
            unlink(self::$manifestPath);
        }
        if (self::$manifestDirCreated && is_dir(dirname(self::$manifestPath))) {
            @rmdir(dirname(self::$manifestPath));
        }
    }

    private function handleKernelRequest(string $method, string $uri): Response
    {
        $saved = $_SERVER;
        $_SERVER = array_merge($saved, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
        ]);

        try {
            return self::$kernel->handle();
        } finally {
            $_SERVER = $saved;
        }
    }
}
