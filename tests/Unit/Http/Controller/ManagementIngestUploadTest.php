<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Http\Controller\ManagementController;
use App\Http\Inertia\InertiaHttpResponder;
use App\Ingestion\Handler\MediaIngestionHandler;
use App\Ingestion\IngestionHandlerRegistry;
use App\Ingestion\Job\TranscribeJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inertia\InertiaFullPageRendererInterface;
use Waaseyaa\Media\LocalFileRepository;
use Waaseyaa\Queue\InMemoryQueue;

#[CoversClass(ManagementController::class)]
final class ManagementIngestUploadTest extends TestCase
{
    private string $storageRoot;

    /** @var string[] */
    private array $uploadedTempPaths = [];

    protected function setUp(): void
    {
        $this->storageRoot = sys_get_temp_dir() . '/giiken-ingest-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedTempPaths as $path) {
            @unlink($path);
        }
        $this->rrmdir($this->storageRoot);
    }

    #[Test]
    public function audio_upload_routes_to_media_handler_persists_file_and_enqueues_transcribe_job(): void
    {
        $fileRepo = new LocalFileRepository($this->storageRoot);
        $queue    = new InMemoryQueue();
        $registry = new IngestionHandlerRegistry();
        $registry->register(new MediaIngestionHandler($fileRepo, $queue));

        $community = Community::make([
            'id'     => 'comm-1',
            'slug'   => 'test-community',
            'name'   => 'Test Community',
            'locale' => 'en',
        ]);

        $communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $communityRepo->method('findBySlug')->with('test-community')->willReturn($community);

        $controller = $this->makeController($communityRepo, $registry);

        $request = $this->requestWithUpload(
            uri:              '/test-community/manage/ingest',
            fileContents:     'ID3 fake mp3 bytes',
            originalFilename: 'field-notes.mp3',
            mimeType:         'audio/mpeg',
        );

        $response = $controller->ingestUpload(
            params: ['communitySlug' => 'test-community'],
            query: [],
            account: $this->anonymousAccount(),
            httpRequest: $request,
        );

        self::assertSame(200, $response->getStatusCode());

        // The media handler must have dispatched exactly one TranscribeJob,
        // carrying the original filename and the community id.
        $messages = $queue->getMessages();
        self::assertCount(1, $messages);
        $job = $messages[0];
        self::assertInstanceOf(TranscribeJob::class, $job);
        self::assertSame('field-notes.mp3', $job->originalFilename);
        self::assertSame('comm-1', $job->communityId);

        // LocalFileRepository must have persisted a file under the storage root.
        $storedFiles = $this->listFilesRecursive($this->storageRoot);
        self::assertNotEmpty($storedFiles, 'Expected MediaIngestionHandler to persist the upload via LocalFileRepository.');
    }

    #[Test]
    public function upload_without_file_rerenders_ingestion_page_with_error(): void
    {
        $registry = new IngestionHandlerRegistry();
        $registry->register(new MediaIngestionHandler(new LocalFileRepository($this->storageRoot), new InMemoryQueue()));

        $community = Community::make([
            'id'     => 'comm-1',
            'slug'   => 'test-community',
            'name'   => 'Test Community',
            'locale' => 'en',
        ]);

        $communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $communityRepo->method('findBySlug')->with('test-community')->willReturn($community);

        $controller = $this->makeController($communityRepo, $registry);

        // No file attached.
        $request = HttpRequest::create('/test-community/manage/ingest', 'POST');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'giiken');

        $response = $controller->ingestUpload(
            params: ['communitySlug' => 'test-community'],
            query: [],
            account: $this->anonymousAccount(),
            httpRequest: $request,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Management/Ingestion', $payload['component']);
        self::assertNotEmpty($payload['props']['uploadError'] ?? null, 'Expected uploadError prop when no file was attached.');
    }

    #[Test]
    public function upload_with_unsupported_mime_rerenders_ingestion_page_with_error(): void
    {
        $fileRepo = new LocalFileRepository($this->storageRoot);
        $registry = new IngestionHandlerRegistry();
        $registry->register(new MediaIngestionHandler($fileRepo, new InMemoryQueue()));

        $community = Community::make([
            'id'     => 'comm-1',
            'slug'   => 'test-community',
            'name'   => 'Test Community',
            'locale' => 'en',
        ]);

        $communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $communityRepo->method('findBySlug')->with('test-community')->willReturn($community);

        $controller = $this->makeController($communityRepo, $registry);

        $request = $this->requestWithUpload(
            uri:              '/test-community/manage/ingest',
            fileContents:     'not really a zip',
            originalFilename: 'secret.zip',
            mimeType:         'application/zip',
        );

        $response = $controller->ingestUpload(
            params: ['communitySlug' => 'test-community'],
            query: [],
            account: $this->anonymousAccount(),
            httpRequest: $request,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Management/Ingestion', $payload['component']);
        self::assertNotEmpty($payload['props']['uploadError'] ?? null);
        self::assertStringContainsString('application/zip', (string) $payload['props']['uploadError']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeController(CommunityRepositoryInterface $communityRepo, IngestionHandlerRegistry $registry): ManagementController
    {
        $renderer = new class implements InertiaFullPageRendererInterface {
            /** @param array<string, mixed> $pageObject */
            public function render(array $pageObject): string
            {
                return '<html><body>test render</body></html>';
            }
        };

        return new ManagementController(
            communityRepo: $communityRepo,
            inertiaHttp: new InertiaHttpResponder($renderer),
            exportService: null,
            handlerRegistry: $registry,
        );
    }

    private function requestWithUpload(
        string $uri,
        string $fileContents,
        string $originalFilename,
        string $mimeType,
    ): HttpRequest {
        $tmpPath = sys_get_temp_dir() . '/giiken-ingest-upload-' . uniqid('', true) . '-' . $originalFilename;
        file_put_contents($tmpPath, $fileContents);
        $this->uploadedTempPaths[] = $tmpPath;

        $upload = new UploadedFile(
            path: $tmpPath,
            originalName: $originalFilename,
            mimeType: $mimeType,
            error: null,
            test: true,
        );

        $request = HttpRequest::create(
            uri: $uri,
            method: 'POST',
            parameters: [],
            cookies: [],
            files: ['file' => $upload],
        );
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', 'giiken');

        return $request;
    }

    private function anonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string { return '0'; }
            public function getRoles(): array { return []; }
            public function isAuthenticated(): bool { return false; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }

    /**
     * @return string[]
     */
    private function listFilesRecursive(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isFile()) {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }

    private function rrmdir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($path);
    }
}
